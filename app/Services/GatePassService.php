<?php

namespace App\Services;

use App\Models\VendorStockLedger;
use Illuminate\Support\Facades\DB;

class GatePassService
{
    public function create(array $data, ?int $userId = null): VendorStockLedger
    {
        return DB::transaction(function () use ($data, $userId) {
            return VendorStockLedger::create([
                'doc_no'         => $this->generateDocNo(),
                'vendor_id'      => $data['vendor_id'],
                'product_id'     => $data['product_id'],
                'status'         => VendorStockLedger::STATUS_FRESH,
                'quantity'       => abs((float) $data['quantity']), // always positive — stock arriving
                'reference_type' => 'GatePass',
                'reference_id'   => null,
                'entry_date'     => $data['entry_date'],
                'remarks'        => $data['remarks'] ?? null,
                'created_by'     => $userId,
            ]);
        });
    }

    public function delete(VendorStockLedger $entry): void
    {
        // Guard: if this fresh stock has already been partially/fully issued
        // to a job, deleting the gate pass could push the vendor's fresh
        // pool negative. Block if that would happen.
        $fresh = VendorStockLedger::balance($entry->vendor_id, $entry->product_id, 'fresh');

        if (($fresh - $entry->quantity) < 0) {
            throw new \Exception(
                'Cannot delete — some of this stock has already been issued to a job. ' .
                'Current fresh balance would go negative.'
            );
        }

        $entry->delete();
    }

    public function vendorStockSummary(?int $vendorId = null)
    {
        $query = VendorStockLedger::query()
            ->selectRaw('vendor_id, product_id, status, SUM(quantity) as qty')
            ->groupBy('vendor_id', 'product_id', 'status')
            ->havingRaw('SUM(quantity) > 0');

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        return $query->with(['vendor:id,name', 'product:id,name,sku'])->get();
    }

    private function generateDocNo(): string
    {
        $last = VendorStockLedger::gatePasses()->orderByDesc('id')->value('doc_no');
        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }
        return 'GP-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}