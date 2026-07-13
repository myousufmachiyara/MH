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

            $jobOrder = JobOrder::findOrFail($data['job_order_id']);

            $receive = JobOrderReceive::create(array_merge($data, [
                'receive_no' => $this->generateReceiveNo(),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));

            foreach ($items as $item) {
                $this->processReceiveLine($jobOrder, $receive, $item);
            }

            // Update job order status based on remaining Issued balance
            $this->refreshJobOrderStatus($jobOrder);

            if ((float) $data['processing_charge'] > 0) {
                $this->postVoucher($receive, $jobOrder, $userId);
            }

            return $receive->load('items.rawProduct', 'items.outputProduct', 'jobOrder.vendor');
        });
    }

    public function delete(JobOrderReceive $receive): void
    {
        DB::transaction(function () use ($receive) {
            $jobOrder = $receive->jobOrder;

            // Reverse: remove the ledger entries this receive created
            // (Issued restored, Leftover removed) and the warehouse stock in.
            VendorStockLedger::where('reference_type', 'JobOrderReceive')
                ->where('reference_id', $receive->id)
                ->delete();

            WarehouseStockMovement::where('reference_type', 'JobOrderReceive')
                ->where('reference_id', $receive->id)
                ->delete();

            $this->voucherService->deleteByReference('JobOrderReceive', $receive->id);

            $receive->items()->delete();
            $receive->delete();

            $this->refreshJobOrderStatus($jobOrder);
        });
    }

    private function processReceiveLine(JobOrder $jobOrder, JobOrderReceive $receive, array $item): void
    {
        $vendorId       = $jobOrder->vendor_id;
        $rawProductId   = $item['raw_product_id'];
        $consumed       = (float) $item['quantity_consumed'];

        // How much of this raw product is currently Issued to this specific job order?
        $issuedToThisJob = VendorStockLedger::where('vendor_id', $vendorId)
            ->where('product_id', $rawProductId)
            ->where('status', 'issued')
            ->where('reference_type', 'JobOrder')
            ->where('reference_id', $jobOrder->id)
            ->sum('quantity');

        // Net of any prior receives already taken against this job order's issue
        $alreadyReceived = VendorStockLedger::where('vendor_id', $vendorId)
            ->where('product_id', $rawProductId)
            ->where('status', 'issued')
            ->where('reference_type', 'JobOrderReceive')
            ->sum('quantity'); // these are negative entries reducing issued

        $stillIssued = $issuedToThisJob + $alreadyReceived; // alreadyReceived is negative

        if ($consumed > $stillIssued) {
            throw new \Exception(
                "Cannot consume {$consumed} of raw product — only {$stillIssued} remains issued for this job order."
            );
        }

        $leftover = $stillIssued - $consumed;
        $leftover = round($leftover, 3);

        JobOrderReceiveItem::create([
            'job_order_receive_id' => $receive->id,
            'raw_product_id'       => $rawProductId,
            'quantity_consumed'    => $consumed,
            'quantity_leftover'    => $leftover,
            'output_product_id'    => $item['output_product_id'] ?? null,
            'quantity_output'      => $item['quantity_output'] ?? 0,
        ]);

        // Remove the full 'stillIssued' amount from Issued (it's all being
        // accounted for now — either consumed, or moved to Leftover).
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

        // Whatever wasn't consumed becomes Leftover, sitting at the vendor,
        // available for a future Job Order.
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

        // Finished/output product arrives at our own warehouse.
        if (!empty($item['output_product_id']) && (float) ($item['quantity_output'] ?? 0) > 0) {
            WarehouseStockMovement::create([
                'product_id'      => $item['output_product_id'],
                'movement_type'   => 'JobReceiveOutput',
                'quantity'        => (float) $item['quantity_output'],
                'amount'          => 0, // valued via processing charge at voucher level, not per-unit here
                'reference_type'  => 'JobOrderReceive',
                'reference_id'    => $receive->id,
                'movement_date'   => $receive->receive_date,
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

    // Dr Processing/Service Cost (or Stock) / Cr Accounts Payable, tagged to vendor
    private function postVoucher(JobOrderReceive $receive, JobOrder $jobOrder, ?int $userId): void
    {
        $processingAccountId = $this->mappingService->accountId('processing_charges');
        $apAccountId          = $this->mappingService->accountId('accounts_payable');

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