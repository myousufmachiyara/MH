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
        // Group ledger rows by doc_no so each gate pass shows as ONE row
        // with its items, not one row per product.
        $rows = VendorStockLedger::gatePasses()
            ->with('vendor', 'product')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('doc_no');

        $gatePasses = $rows->map(function ($items, $docNo) {
            $first = $items->first();
            return (object) [
                'doc_no'     => $docNo,
                'vendor'     => $first->vendor,
                'entry_date' => $first->entry_date,
                'remarks'    => $first->remarks,
                'items'      => $items,
            ];
        })->values();

        return view('gate_passes.index', compact('gatePasses'));
    }

    public function create()
    {
        $vendors  = Vendor::active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();

        return view('gate_passes.create', compact('vendors', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id'          => 'required|exists:vendors,id',
            'entry_date'         => 'required|date',
            'remarks'            => 'nullable|string|max:500',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
        ]);

        try {
            $docNo = $this->service->create([
                'vendor_id'  => $request->vendor_id,
                'entry_date' => $request->entry_date,
                'remarks'    => $request->remarks,
            ], $request->items, auth()->id());

            Log::info('[GatePass] Created', ['doc_no' => $docNo, 'by' => auth()->id()]);

            return redirect()->route('gate_passes.index')
                ->with('success', 'Gate pass ' . $docNo . ' created successfully.');

        } catch (\Exception $e) {
            Log::error('[GatePass] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit($docNo)
    {
        $entries = VendorStockLedger::gatePasses()
            ->with('vendor', 'product')
            ->where('doc_no', $docNo)
            ->get();

        if ($entries->isEmpty()) {
            abort(404);
        }

        $first = $entries->first();
        $gatePass = (object) [
            'doc_no'     => $docNo,
            'vendor_id'  => $first->vendor_id,
            'entry_date' => $first->entry_date,
            'remarks'    => $first->remarks,
            'items'      => $entries,
        ];

        $vendors  = Vendor::active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();

        return view('gate_passes.edit', compact('gatePass', 'vendors', 'products'));
    }

    public function update(Request $request, $docNo)
    {
        $request->validate([
            'vendor_id'          => 'required|exists:vendors,id',
            'entry_date'         => 'required|date',
            'remarks'            => 'nullable|string|max:500',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
        ]);

        try {
            $this->service->update($docNo, [
                'vendor_id'  => $request->vendor_id,
                'entry_date' => $request->entry_date,
                'remarks'    => $request->remarks,
            ], $request->items, auth()->id());

            Log::info('[GatePass] Updated', ['doc_no' => $docNo, 'by' => auth()->id()]);

            return redirect()->route('gate_passes.index')
                ->with('success', 'Gate pass updated successfully.');

        } catch (\Exception $e) {
            Log::error('[GatePass] Update failed', ['doc_no' => $docNo, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
    
    public function destroy($docNo)
    {
        try {
            $this->service->deleteByDocNo($docNo);

            return redirect()->route('gate_passes.index')
                ->with('success', 'Gate pass deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[GatePass] Destroy failed', ['doc_no' => $docNo, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function print($docNo)
    {
        abort(404, 'Print not yet implemented');
    }
}