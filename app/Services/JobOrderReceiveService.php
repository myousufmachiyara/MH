<?php

namespace App\Services;

use App\Models\JobOrder;
use App\Models\JobOrderReceive;
use App\Models\JobOrderReceiveItem;
use App\Models\JobOrderReceiveOutput;
use App\Models\VendorStockLedger;
use App\Models\WarehouseStockMovement;
use Illuminate\Support\Facades\DB;

class JobOrderReceiveService
{
    public function __construct(
        private VoucherService $voucherService,
        private AccountMappingService $mappingService
    ) {}

    // $consumedItems: [ ['raw_product_id'=>.., 'quantity_consumed'=>..], ... ]
    // $outputItems:   [ ['output_product_id'=>.., 'quantity_output'=>.., 'conversion_rate'=>..], ... ]
    public function create(array $data, array $consumedItems, array $outputItems, ?int $userId = null): JobOrderReceive
    {
        return DB::transaction(function () use ($data, $consumedItems, $outputItems, $userId) {

            $jobOrder = JobOrder::with('jobType')->findOrFail($data['job_order_id']);

            $calculatedTotal = $this->calculateTotalProcessingAmount($outputItems);
            $processingCharge = isset($data['processing_charge_override']) && $data['processing_charge_override'] !== null
                ? (float) $data['processing_charge_override']
                : $calculatedTotal;

            $receive = JobOrderReceive::create([
                'receive_no'        => $this->generateReceiveNo(),
                'job_order_id'      => $jobOrder->id,
                'receive_date'      => $data['receive_date'],
                'processing_charge' => $processingCharge,
                'remarks'           => $data['remarks'] ?? null,
                'attachments'       => $data['attachments'] ?? null,
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);

            // Consume raw materials — merge duplicate rows for the same product first
            $mergedConsumed = $this->mergeConsumedByProduct($consumedItems);
            foreach ($mergedConsumed as $line) {
                $this->consumeRawLine($jobOrder, $receive, $line);
            }

            // Record outputs — independent of raw lines, each priced separately
            foreach ($outputItems as $output) {
                $qty  = (float) $output['quantity_output'];
                $rate = (float) ($output['conversion_rate'] ?? 0);
                if ($qty <= 0) continue;

                $amount = round($qty * $rate, 2);

                JobOrderReceiveOutput::create([
                    'job_order_receive_id' => $receive->id,
                    'output_product_id'    => $output['output_product_id'],
                    'quantity_output'      => $qty,
                    'conversion_rate'      => $rate,
                    'processing_amount'    => $amount,
                ]);

                WarehouseStockMovement::create([
                    'product_id'      => $output['output_product_id'],
                    'movement_type'   => 'JobReceiveOutput',
                    'quantity'        => $qty,
                    'amount'          => $amount,
                    'reference_type'  => 'JobOrderReceive',
                    'reference_id'    => $receive->id,
                    'movement_date'   => $receive->receive_date,
                ]);
            }

            $this->refreshJobOrderStatus($jobOrder);

            if ($receive->processing_charge > 0) {
                $this->postVoucher($receive, $jobOrder, $userId);
            }

            return $receive->load('items.rawProduct', 'outputs.outputProduct', 'jobOrder.vendor');
        });
    }

    public function update(JobOrderReceive $receive, array $data, array $consumedItems, array $outputItems, ?int $userId = null): JobOrderReceive
    {
        return DB::transaction(function () use ($receive, $data, $consumedItems, $outputItems, $userId) {

            $jobOrder = $receive->jobOrder()->with('jobType')->first();

            VendorStockLedger::where('reference_type', 'JobOrderReceive')
                ->where('reference_id', $receive->id)
                ->delete();

            WarehouseStockMovement::where('reference_type', 'JobOrderReceive')
                ->where('reference_id', $receive->id)
                ->delete();

            $this->voucherService->deleteByReference('JobOrderReceive', $receive->id);

            $receive->items()->delete();
            $receive->outputs()->delete();

            $calculatedTotal = $this->calculateTotalProcessingAmount($outputItems);
            $processingCharge = isset($data['processing_charge_override']) && $data['processing_charge_override'] !== null
                ? (float) $data['processing_charge_override']
                : $calculatedTotal;

            $receive->update([
                'receive_date'       => $data['receive_date'],
                'processing_charge'  => $processingCharge,
                'remarks'            => $data['remarks'] ?? null,
                'attachments'        => $data['attachments'] ?? $receive->attachments,
                'updated_by'         => $userId,
            ]);

            $mergedConsumed = $this->mergeConsumedByProduct($consumedItems);
            foreach ($mergedConsumed as $line) {
                $this->consumeRawLine($jobOrder, $receive, $line);
            }

            foreach ($outputItems as $output) {
                $qty  = (float) $output['quantity_output'];
                $rate = (float) ($output['conversion_rate'] ?? 0);
                if ($qty <= 0) continue;

                $amount = round($qty * $rate, 2);

                JobOrderReceiveOutput::create([
                    'job_order_receive_id' => $receive->id,
                    'output_product_id'    => $output['output_product_id'],
                    'quantity_output'      => $qty,
                    'conversion_rate'      => $rate,
                    'processing_amount'    => $amount,
                ]);

                WarehouseStockMovement::create([
                    'product_id'      => $output['output_product_id'],
                    'movement_type'   => 'JobReceiveOutput',
                    'quantity'        => $qty,
                    'amount'          => $amount,
                    'reference_type'  => 'JobOrderReceive',
                    'reference_id'    => $receive->id,
                    'movement_date'   => $receive->receive_date,
                ]);
            }

            $this->refreshJobOrderStatus($jobOrder);

            if ($receive->processing_charge > 0) {
                $this->postVoucher($receive, $jobOrder, $userId);
            }

            return $receive->load('items.rawProduct', 'outputs.outputProduct', 'jobOrder.vendor');
        });
    }

    public function delete(JobOrderReceive $receive): void
    {
        DB::transaction(function () use ($receive) {
            $jobOrder = $receive->jobOrder;

            VendorStockLedger::where('reference_type', 'JobOrderReceive')
                ->where('reference_id', $receive->id)
                ->delete();

            WarehouseStockMovement::where('reference_type', 'JobOrderReceive')
                ->where('reference_id', $receive->id)
                ->delete();

            $this->voucherService->deleteByReference('JobOrderReceive', $receive->id);

            $receive->items()->delete();
            $receive->outputs()->delete();
            $receive->delete();

            if ($jobOrder) {
                $this->refreshJobOrderStatus($jobOrder);
            }
        });
    }

    private function calculateTotalProcessingAmount(array $outputItems): float
    {
        $total = 0;
        foreach ($outputItems as $output) {
            $qty  = (float) ($output['quantity_output'] ?? 0);
            $rate = (float) ($output['conversion_rate'] ?? 0);
            $total += $qty * $rate;
        }
        return round($total, 2);
    }

    // Combine multiple rows consuming the same raw product (e.g. if the
    // form allowed picking a product twice by mistake) into one quantity.
    private function mergeConsumedByProduct(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $key = $item['raw_product_id'];
            $merged[$key] = ($merged[$key] ?? 0) + (float) ($item['quantity_consumed'] ?? 0);
        }

        $result = [];
        foreach ($merged as $productId => $qty) {
            if ($qty > 0) {
                $result[] = ['raw_product_id' => $productId, 'quantity_consumed' => $qty];
            }
        }
        return $result;
    }

    private function consumeRawLine(JobOrder $jobOrder, JobOrderReceive $receive, array $line): void
    {
        $vendorId     = $jobOrder->vendor_id;
        $rawProductId = $line['raw_product_id'];
        $consumed     = (float) $line['quantity_consumed'];

        $issuedToThisJob = VendorStockLedger::where('vendor_id', $vendorId)
            ->where('product_id', $rawProductId)
            ->where('status', 'issued')
            ->where('reference_type', 'JobOrder')
            ->where('reference_id', $jobOrder->id)
            ->sum('quantity');

        $alreadyReceived = VendorStockLedger::where('vendor_id', $vendorId)
            ->where('product_id', $rawProductId)
            ->where('status', 'issued')
            ->where('reference_type', 'JobOrderReceive')
            ->sum('quantity');

        $stillIssued = $issuedToThisJob + $alreadyReceived;

        if ($consumed > $stillIssued + 0.001) {
            throw new \Exception(
                "Cannot consume {$consumed} of raw product — only " . round($stillIssued, 3) .
                " remains issued for this job order."
            );
        }

        $leftover = round($stillIssued - $consumed, 3);

        JobOrderReceiveItem::create([
            'job_order_receive_id' => $receive->id,
            'raw_product_id'       => $rawProductId,
            'quantity_consumed'    => $consumed,
            'quantity_leftover'    => $leftover,
        ]);

        VendorStockLedger::create([
            'vendor_id'      => $vendorId,
            'product_id'     => $rawProductId,
            'status'         => 'issued',
            'quantity'       => -$stillIssued,
            'reference_type' => 'JobOrderReceive',
            'reference_id'   => $receive->id,
            'entry_date'     => $receive->receive_date,
            'created_by'     => $receive->created_by,
        ]);

        if ($leftover > 0) {
            VendorStockLedger::create([
                'vendor_id'      => $vendorId,
                'product_id'     => $rawProductId,
                'status'         => 'leftover',
                'quantity'       => $leftover,
                'reference_type' => 'JobOrderReceive',
                'reference_id'   => $receive->id,
                'entry_date'     => $receive->receive_date,
                'created_by'     => $receive->created_by,
            ]);
        }
    }

    private function refreshJobOrderStatus(JobOrder $jobOrder): void
    {
        $remainingIssued = VendorStockLedger::where('vendor_id', $jobOrder->vendor_id)
            ->where(function ($q) use ($jobOrder) {
                $q->where(fn($q2) => $q2->where('reference_type', 'JobOrder')->where('reference_id', $jobOrder->id))
                  ->orWhereIn('reference_id', $jobOrder->receives()->pluck('id'));
            })
            ->where('status', 'issued')
            ->sum('quantity');

        $status = $jobOrder->receives()->exists()
            ? ($remainingIssued > 0.001 ? 'PartiallyReceived' : 'Received')
            : 'Issued';

        $jobOrder->update(['status' => $status]);
    }

    private function postVoucher(JobOrderReceive $receive, JobOrder $jobOrder, ?int $userId): void
    {
        $processingAccountId = $jobOrder->jobType?->service_cost_account_id
            ?? $this->mappingService->accountId('processing_charges');

        $apAccountId = $this->mappingService->accountId('accounts_payable');

        $lines = [
            [
                'account_id' => $processingAccountId,
                'debit'      => $receive->processing_charge,
                'credit'     => 0,
            ],
            [
                'account_id' => $apAccountId,
                'debit'      => 0,
                'credit'     => $receive->processing_charge,
                'party_type' => 'vendor',
                'party_id'   => $jobOrder->vendor_id,
            ],
        ];

        $this->voucherService->post(
            'system',
            $receive->receive_date->format('Y-m-d'),
            $lines,
            "Job Receive {$receive->receive_no} — processing charge for {$jobOrder->job_no}",
            'JobOrderReceive',
            $receive->id,
            $userId
        );
    }

    private function generateReceiveNo(): string
    {
        $last = JobOrderReceive::orderByDesc('id')->value('receive_no');
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return 'JR-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}