<?php

namespace App\Http\Controllers;

use App\Models\VendorStockLedger;
use App\Models\Vendor;
use App\Models\Product;
use App\Services\GatePassService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GatePassController extends Controller
{
    public function __construct(private GatePassService $service) {}

    public function index()
    {
        $gatePasses = VendorStockLedger::gatePasses()
            ->with('vendor', 'product')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        return view('gate_passes.index', compact('gatePasses'));
    }

    public function create()
    {
        $vendors  = Vendor::active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();

        return view('gate_passes.create', compact('vendors', 'products'));
    }

    public function store(array $data, ?int $userId = null): VendorStockLedger
    {
        return DB::transaction(function () use ($data, $userId) {
            $docNo = $this->generateDocNo();

            $entry = VendorStockLedger::create([
                'doc_no'         => $docNo,
                'vendor_id'      => $data['vendor_id'],
                'product_id'     => $data['product_id'],
                'status'         => VendorStockLedger::STATUS_FRESH,
                'quantity'       => abs((float) $data['quantity']),
                'reference_type' => 'GatePass',
                'reference_id'   => null,
                'entry_date'     => $data['entry_date'],
                'remarks'        => $data['remarks'] ?? null,
                'created_by'     => $userId,
            ]);

            // Reduce our own warehouse stock — this material has physically left.
            WarehouseStockMovement::create([
                'product_id'      => $data['product_id'],
                'movement_type'   => 'GatePassOut',
                'quantity'        => -abs((float) $data['quantity']),
                'amount'          => 0, // no value change, just relocation
                'reference_type'  => 'GatePass',
                'reference_id'    => $entry->id,
                'movement_date'   => $data['entry_date'],
            ]);

            return $entry;
        });
    }
    
    public function destroy(VendorStockLedger $entry): void
    {
        $fresh = VendorStockLedger::balance($entry->vendor_id, $entry->product_id, 'fresh');

        if (($fresh - $entry->quantity) < 0) {
            throw new \Exception(
                'Cannot delete — some of this stock has already been issued to a job.'
            );
        }

        WarehouseStockMovement::where('reference_type', 'GatePass')
            ->where('reference_id', $entry->id)
            ->delete();

        $entry->delete();
    }

    public function print($id)
    {
        abort(404, 'Print not yet implemented');
    }
}