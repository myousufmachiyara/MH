<?php

namespace App\Services;

use App\Models\VendorStockLedger;
use App\Models\WarehouseStockMovement;
use Illuminate\Support\Facades\DB;

class GatePassService
{
    // $data: vendor_id, entry_date, remarks
    // $items: [ ['product_id' => .., 'quantity' => ..], ... ]
    public function create(array $data, array $items, ?int $userId = null): string
    {
        return DB::transaction(function () use ($data, $items, $userId) {
            $docNo = $this->generateDocNo();

            foreach ($items as $item) {
                $qty = abs((float) $item['quantity']);

                VendorStockLedger::create([
                    'doc_no'         => $docNo,
                    'vendor_id'      => $data['vendor_id'],
                    'product_id'     => $item['product_id'],
                    'status'         => VendorStockLedger::STATUS_FRESH,
                    'quantity'       => $qty,
                    'reference_type' => 'GatePass',
                    'reference_id'   => null,
                    'entry_date'     => $data['entry_date'],
                    'remarks'        => $data['remarks'] ?? null,
                    'created_by'     => $userId,
                ]);

                WarehouseStockMovement::create([
                    'product_id'      => $item['product_id'],
                    'movement_type'   => 'GatePassOut',
                    'quantity'        => -$qty,
                    'amount'          => 0,
                    'reference_type'  => 'GatePass',
                    'reference_id'    => null,
                    'doc_no'          => $docNo,
                    'movement_date'   => $data['entry_date'],
                ]);
            }

            return $docNo;
        });
    }

    public function update(string $docNo, array $data, array $items, ?int $userId = null): string
    {
        return DB::transaction(function () use ($docNo, $data, $items, $userId) {

            $existingEntries = VendorStockLedger::gatePasses()->where('doc_no', $docNo)->get();

            // Guard: block edit if any of the original items have already
            // been (partially or fully) issued to a job — editing would
            // corrupt the Fresh pool those issues drew from.
            foreach ($existingEntries as $entry) {
                $fresh = VendorStockLedger::balance($entry->vendor_id, $entry->product_id, 'fresh');
                if (($fresh - $entry->quantity) < 0) {
                    throw new \Exception(
                        "Cannot edit — {$entry->product?->name} has already been issued to a job. " .
                        "Delete is also blocked for the same reason; the stock must remain traceable."
                    );
                }
            }

            // Reverse the old entries entirely
            WarehouseStockMovement::where('reference_type', 'GatePass')
                ->where('doc_no', $docNo)
                ->delete();

            $existingEntries->each->delete();

            // Recreate with the new data, same doc_no preserved
            foreach ($items as $item) {
                $qty = abs((float) $item['quantity']);

                VendorStockLedger::create([
                    'doc_no'         => $docNo,
                    'vendor_id'      => $data['vendor_id'],
                    'product_id'     => $item['product_id'],
                    'status'         => VendorStockLedger::STATUS_FRESH,
                    'quantity'       => $qty,
                    'reference_type' => 'GatePass',
                    'reference_id'   => null,
                    'entry_date'     => $data['entry_date'],
                    'remarks'        => $data['remarks'] ?? null,
                    'created_by'     => $userId,
                ]);

                WarehouseStockMovement::create([
                    'product_id'      => $item['product_id'],
                    'movement_type'   => 'GatePassOut',
                    'quantity'        => -$qty,
                    'amount'          => 0,
                    'reference_type'  => 'GatePass',
                    'reference_id'    => null,
                    'doc_no'          => $docNo,
                    'movement_date'   => $data['entry_date'],
                ]);
            }

            return $docNo;
        });
    }
    
    public function deleteByDocNo(string $docNo): void
    {
        DB::transaction(function () use ($docNo) {
            $entries = VendorStockLedger::gatePasses()->where('doc_no', $docNo)->get();

            foreach ($entries as $entry) {
                $fresh = VendorStockLedger::balance($entry->vendor_id, $entry->product_id, 'fresh');
                if (($fresh - $entry->quantity) < 0) {
                    throw new \Exception(
                        "Cannot delete — {$entry->product?->name} has already been issued to a job."
                    );
                }
            }

            WarehouseStockMovement::where('reference_type', 'GatePass')
                ->where('doc_no', $docNo)
                ->delete();

            $entries->each->delete();
        });
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