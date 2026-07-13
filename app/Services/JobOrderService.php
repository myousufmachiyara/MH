<?php

namespace App\Services;

use App\Models\JobOrder;
use App\Models\JobOrderItem;
use App\Models\VendorStockLedger;
use Illuminate\Support\Facades\DB;

class JobOrderService
{
    public function create(array $data, array $items, ?int $userId = null): JobOrder
    {
        return DB::transaction(function () use ($data, $items, $userId) {

            $jobOrder = JobOrder::create(array_merge($data, [
                'job_no'     => $this->generateJobNo(),
                'status'     => 'Issued',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));

            foreach ($items as $item) {
                $this->issueStock($jobOrder, $item['product_id'], (float) $item['quantity']);
            }

            return $jobOrder->load('items.product', 'vendor');
        });
    }

    public function delete(JobOrder $jobOrder): void
    {
        DB::transaction(function () use ($jobOrder) {
            if ($jobOrder->receives()->exists()) {
                throw new \Exception('Cannot delete — this job order already has receives recorded.');
            }

            // Reverse the issue: remove the ledger entries this job order created
            VendorStockLedger::where('reference_type', 'JobOrder')
                ->where('reference_id', $jobOrder->id)
                ->delete();

            $jobOrder->items()->delete();
            $jobOrder->delete();
        });
    }

    // Draws quantity from Leftover first, then Fresh. Writes the ledger
    // entries and the job_order_items line.
    private function issueStock(JobOrder $jobOrder, int $productId, float $quantity): void
    {
        $vendorId = $jobOrder->vendor_id;

        $leftoverBalance = VendorStockLedger::balance($vendorId, $productId, 'leftover');
        $freshBalance    = VendorStockLedger::balance($vendorId, $productId, 'fresh');

        if (($leftoverBalance + $freshBalance) < $quantity) {
            throw new \Exception(
                "Insufficient stock at vendor for this product. Available: " .
                round($leftoverBalance + $freshBalance, 3) . ", requested: {$quantity}."
            );
        }

        $fromLeftover = min($leftoverBalance, $quantity);
        $fromFresh    = $quantity - $fromLeftover;

        $sourceStatus = $fromLeftover > 0 && $fromFresh > 0
            ? 'mixed'
            : ($fromLeftover > 0 ? 'leftover' : 'fresh');

        JobOrderItem::create([
            'job_order_id'  => $jobOrder->id,
            'product_id'    => $productId,
            'quantity'      => $quantity,
            'source_status' => $sourceStatus,
        ]);

        if ($fromLeftover > 0) {
            VendorStockLedger::create([
                'vendor_id'      => $vendorId,
                'product_id'     => $productId,
                'status'         => 'leftover',
                'quantity'       => -$fromLeftover,
                'reference_type' => 'JobOrder',
                'reference_id'   => $jobOrder->id,
                'entry_date'     => $jobOrder->issue_date,
                'created_by'     => $jobOrder->created_by,
            ]);
        }

        if ($fromFresh > 0) {
            VendorStockLedger::create([
                'vendor_id'      => $vendorId,
                'product_id'     => $productId,
                'status'         => 'fresh',
                'quantity'       => -$fromFresh,
                'reference_type' => 'JobOrder',
                'reference_id'   => $jobOrder->id,
                'entry_date'     => $jobOrder->issue_date,
                'created_by'     => $jobOrder->created_by,
            ]);
        }

        VendorStockLedger::create([
            'vendor_id'      => $vendorId,
            'product_id'     => $productId,
            'status'         => 'issued',
            'quantity'       => $quantity,
            'reference_type' => 'JobOrder',
            'reference_id'   => $jobOrder->id,
            'entry_date'     => $jobOrder->issue_date,
            'created_by'     => $jobOrder->created_by,
        ]);
    }

    private function generateJobNo(): string
    {
        $last = JobOrder::orderByDesc('id')->value('job_no');
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return 'JOB-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}