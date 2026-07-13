<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReturn;
use App\Models\Purchase;
use App\Services\PurchaseReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseReturnController extends Controller
{
    public function __construct(private PurchaseReturnService $service) {}

    public function index(Request $request)
    {
        $returns = PurchaseReturn::with('vendor', 'purchase')
            ->when($request->boolean('view_deleted'), fn($q) => $q->onlyTrashed())
            ->orderByDesc('return_date')
            ->get();

        return view('purchase_returns.index', compact('returns'));
    }

    public function create()
    {
        $purchases = Purchase::with('vendor', 'items.product')->orderByDesc('purchase_date')->get();

        return view('purchase_returns.create', compact('purchases'));
    }

    // AJAX: get a purchase's items for the return form
    public function purchaseItems($purchaseId)
    {
        $purchase = Purchase::with('items.product')->findOrFail($purchaseId);

        return response()->json([
            'vendor_id' => $purchase->vendor_id,
            'items'     => $purchase->items->map(fn($i) => [
                'purchase_item_id' => $i->id,
                'product_id'       => $i->product_id,
                'product_name'     => $i->product?->name,
                'purchased_qty'    => (float) $i->quantity,
                'unit_price'       => (float) $i->unit_price,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_id'                  => 'required|exists:purchases,id',
            'return_date'                  => 'required|date',
            'remarks'                      => 'nullable|string',
            'items'                        => 'required|array|min:1',
            'items.*.purchase_item_id'     => 'required|exists:purchase_items,id',
            'items.*.product_id'           => 'required|exists:products,id',
            'items.*.quantity'             => 'required|numeric|min:0.001',
            'items.*.unit_price'           => 'required|numeric|min:0',
        ]);

        try {
            $purchase = Purchase::findOrFail($request->purchase_id);

            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('purchase_return_attachments', 'public');
                }
            }

            $return = $this->service->create([
                'purchase_id'   => $purchase->id,
                'vendor_id'     => $purchase->vendor_id,
                'return_date'   => $request->return_date,
                'remarks'       => $request->remarks,
                'attachments'   => $attachments ?: null,
            ], $request->items, auth()->id());

            Log::info('[PurchaseReturn] Created', ['id' => $return->id, 'by' => auth()->id()]);

            return redirect()->route('purchase_returns.index')
                ->with('success', 'Purchase return ' . $return->return_no . ' created successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseReturn] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $return = PurchaseReturn::findOrFail($id);
            $this->service->delete($return);

            return redirect()->route('purchase_returns.index')
                ->with('success', 'Purchase return deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseReturn] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete purchase return.');
        }
    }

    public function print($id)
    {
        abort(404, 'Print not yet implemented');
    }

    public function edit($id)
    {
        $return = PurchaseReturn::with('items.product', 'items.purchaseItem', 'purchase', 'vendor')->findOrFail($id);

        return view('purchase_returns.edit', compact('return'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'return_date'                  => 'required|date',
            'remarks'                      => 'nullable|string',
            'items'                        => 'required|array|min:1',
            'items.*.purchase_item_id'     => 'required|exists:purchase_items,id',
            'items.*.product_id'           => 'required|exists:products,id',
            'items.*.quantity'             => 'required|numeric|min:0.001',
            'items.*.unit_price'           => 'required|numeric|min:0',
        ]);

        try {
            $return = PurchaseReturn::findOrFail($id);

            $attachments = $return->attachments ?? [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('purchase_return_attachments', 'public');
                }
            }

            $this->service->update($return, [
                'return_date' => $request->return_date,
                'remarks'     => $request->remarks,
                'attachments' => $attachments ?: null,
            ], $request->items, auth()->id());

            Log::info('[PurchaseReturn] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('purchase_returns.index')
                ->with('success', 'Purchase return updated successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseReturn] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}