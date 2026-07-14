<?php

namespace App\Services;

use App\Models\JobOrder;
use App\Models\JobOrderReceive;
use App\Models\JobOrderReceiveItem;
use App\Models\VendorStockLedger;
use App\Models\WarehouseStockMovement;
use Illuminate\Support\Facades\DB;

class JobOrderReceiveService
{
    public function __construct(
        private VoucherService $voucherService,
        private AccountMappingService $mappingService
    ) {}

    public function create(array $data, array $items, ?int $userId = null): JobOrderReceive
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $jobOrder = JobOrder::with('jobType')->findOrFail($data['job_order_id']);

            $receive = JobOrderReceive::create([
                'receive_no'        => $this->generateReceiveNo(),
                'job_order_id'      => $jobOrder->id,
                'receive_date'      => $data['receive_date'],
                'processing_charge' => $data['processing_charge'] ?? 0,
                'remarks'           => $data['remarks'] ?? null,
                'attachments'       => $data['attachments'] ?? null,
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);

            $merged = $this->mergeItemsByRawProduct($items);

            foreach ($merged as $line) {
                $this->processReceiveLine($jobOrder, $receive, $line);
            }

            $this->refreshJobOrderStatus($jobOrder);

            if ((float) $receive->processing_charge > 0) {
                $this->postVoucher($receive, $jobOrder, $userId);
            }

            return $receive->load('items.rawProduct', 'items.outputProduct', 'jobOrder.vendor');
        });
    }

    public function update(JobOrderReceive $receive, array $data, array $items, ?int $userId = null): JobOrderReceive
    {
        return DB::transaction(function () use ($receive, $data, $items, $userId) {

            $jobOrder = $receive->jobOrder()->with('jobType')->first();

            // Reverse everything this receive previously created
            VendorStockLedger::where('reference_type', 'JobOrderReceive')
                ->where('reference_id', $receive->id)
                ->delete();

            WarehouseStockMovement::where('reference_type', 'JobOrderReceive')
                ->where('reference_id', $receive->id)
                ->delete();

            $this->voucherService->deleteByReference('JobOrderReceive', $receive->id);

            $receive->items()->delete();

            // Update header fields
            $receive->update([
                'receive_date'       => $data['receive_date'],
                'processing_charge'  => $data['processing_charge'] ?? 0,
                'remarks'            => $data['remarks'] ?? null,
                'attachments'        => $data['attachments'] ?? $receive->attachments,
                'updated_by'         => $userId,
            ]);

            $merged = $this->mergeItemsByRawProduct($items);

            foreach ($merged as $line) {
                $this->processReceiveLine($jobOrder, $receive, $line);
            }

            $this->refreshJobOrderStatus($jobOrder);

            if ((float) $receive->processing_charge > 0) {
                $this->postVoucher($receive, $jobOrder, $userId);
            }

            return $receive->load('items.rawProduct', 'items.outputProduct', 'jobOrder.vendor');
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
            $receive->delete();

            if ($jobOrder) {
                $this->refreshJobOrderStatus($jobOrder);
            }
        });
    }

    // Combine rows that target the same raw_product_id into a single
    // line with a summed quantity_consumed and a list of outputs.
    // Prevents double-subtracting "still issued" when the form submits
    // multiple rows for one raw product (e.g. split into two outputs).
    private function mergeItemsByRawProduct(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            $key = $item['raw_product_id'];

            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'raw_product_id'    => $item['raw_product_id'],
                    'quantity_consumed' => 0,
                    'outputs'           => [],
                ];
            }

            $merged[$key]['quantity_consumed'] += (float) ($item['quantity_consumed'] ?? 0);

            if (!empty($item['output_product_id']) && (float) ($item['quantity_output'] ?? 0) > 0) {
                $merged[$key]['outputs'][] = [
                    'output_product_id' => $item['output_product_id'],
                    'quantity_output'   => (float) $item['quantity_output'],
                ];
            }
        }

        return array_values($merged);
    }

    private function processReceiveLine(JobOrder $jobOrder, JobOrderReceive $receive, array $line): void
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

        // One JobOrderReceiveItem row per output (or one row with no
        // output if none was specified), all sharing the same
        // consumed/leftover figures for this raw product. Only the
        // first row carries the consumed/leftover values, to avoid
        // double-counting them across multiple output rows in reports.
        $outputs = !empty($line['outputs']) ? $line['outputs'] : [['output_product_id' => null, 'quantity_output' => 0]];

        foreach ($outputs as $i => $output) {
            JobOrderReceiveItem::create([
                'job_order_receive_id' => $receive->id,
                'raw_product_id'       => $rawProductId,
                'quantity_consumed'    => $i === 0 ? $consumed : 0,
                'quantity_leftover'    => $i === 0 ? $leftover : 0,
                'output_product_id'    => $output['output_product_id'],
                'quantity_output'      => $output['quantity_output'],
            ]);

            if (!empty($output['output_product_id']) && $output['quantity_output'] > 0) {
                WarehouseStockMovement::create([
                    'product_id'      => $output['output_product_id'],
                    'movement_type'   => 'JobReceiveOutput',
                    'quantity'        => $output['quantity_output'],
                    'amount'          => 0,
                    'reference_type'  => 'JobOrderReceive',
                    'reference_id'    => $receive->id,
                    'movement_date'   => $receive->receive_date,
                ]);
            }
        }

        // Remove the full 'stillIssued' amount from Issued — it's all
        // being accounted for now, either consumed or moved to Leftover.
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

        // Whatever wasn't consumed becomes Leftover, sitting at the
        // vendor, available for a future Job Order.
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

    // If nothing remains Issued for this job order across all its raw
    // products, it's fully Received. Otherwise PartiallyReceived.
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

    // Dr Processing/Service Cost (resolved from the job's job_type,
    // falling back to the global 'processing_charges' mapping)
    // / Cr Accounts Payable, tagged to vendor
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