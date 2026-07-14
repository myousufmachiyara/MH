<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseInvoiceController extends Controller
{
    public function __construct(private PurchaseService $purchaseService) {}

    public function index(Request $request)
    {
        $query = Purchase::with('vendor')
            ->when($request->boolean('view_deleted'), fn($q) => $q->onlyTrashed())
            ->orderByDesc('purchase_date');

        $invoices = $query->get();

        return view('purchases.index', compact('invoices'));
    }

    public function create(Request $request)
    {
        $vendors  = Vendor::active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();
        $units    = MeasurementUnit::orderBy('name')->get();

        $fromOrder = null;
        if ($request->filled('from_order')) {
            $fromOrder = \App\Models\PurchaseOrder::with('items.product')
                ->findOrFail($request->from_order);
        }

        return view('purchases.create', compact('vendors', 'products', 'units', 'fromOrder'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_date'       => 'required|date',
            'vendor_id'          => 'required|exists:vendors,id',
            'bill_no'            => 'nullable|string|max:50',
            'ref_no'             => 'nullable|string|max:50',
            'remarks'            => 'nullable|string',
            'from_order_id'      => 'nullable|exists:purchase_orders,id',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.price'      => 'required|numeric|min:0',
        ]);

        try {
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('purchase_attachments', 'public');
                }
            }

            $items = collect($request->items)->map(fn($i) => [
                'product_id'           => $i['item_id'],
                'product_variation_id' => $i['variation_id'] ?? null,
                'quantity'             => $i['quantity'],
                'unit_price'           => $i['price'],
            ])->toArray();

            $purchase = $this->purchaseService->create([
                'vendor_id'     => $request->vendor_id,
                'order_id'      => $request->from_order_id, // links purchases.order_id → purchase_orders
                'purchase_date' => $request->invoice_date,
                'bill_no'       => $request->bill_no,
                'ref_no'        => $request->ref_no,
                'remarks'       => $request->remarks,
                'attachments'   => $attachments ?: null,
            ], $items, auth()->id());

            // Mark the source PO as Converted so it won't be offered for conversion again
            if ($request->filled('from_order_id')) {
                \App\Models\PurchaseOrder::where('id', $request->from_order_id)
                    ->update(['status' => 'Converted']);
            }

            Log::info('[Purchase] Created', ['id' => $purchase->id, 'by' => auth()->id()]);

            return redirect()->route('purchase_invoices.index')
                ->with('success', 'Purchase invoice ' . $purchase->purchase_no . ' created successfully.');

        } catch (\Exception $e) {
            Log::error('[Purchase] Store failed', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit($id)
    {
        $purchase = Purchase::with('items.product', 'items.variation')->findOrFail($id);
        $vendors  = Vendor::active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();
        $units    = MeasurementUnit::orderBy('name')->get();

        return view('purchases.edit', compact('purchase', 'vendors', 'products', 'units'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'invoice_date'       => 'required|date',
            'vendor_id'          => 'required|exists:vendors,id',
            'bill_no'            => 'nullable|string|max:50',
            'ref_no'             => 'nullable|string|max:50',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.price'      => 'required|numeric|min:0',
        ]);

        try {
            $purchase = Purchase::findOrFail($id);

            $attachments = $purchase->attachments ?? [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('purchase_attachments', 'public');
                }
            }

            $items = collect($request->items)->map(fn($i) => [
                'product_id'           => $i['item_id'],
                'product_variation_id' => $i['variation_id'] ?? null,
                'quantity'             => $i['quantity'],
                'unit_price'           => $i['price'],
            ])->toArray();

            $this->purchaseService->update($purchase, [
                'vendor_id'     => $request->vendor_id,
                'purchase_date' => $request->invoice_date,
                'bill_no'       => $request->bill_no,
                'ref_no'        => $request->ref_no,
                'remarks'       => $request->remarks,
                'attachments'   => $attachments ?: null,
            ], $items, auth()->id());

            Log::info('[Purchase] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('purchase_invoices.index')
                ->with('success', 'Purchase invoice updated successfully.');

        } catch (\Exception $e) {
            Log::error('[Purchase] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $purchase = Purchase::findOrFail($id);
            $this->purchaseService->delete($purchase);

            Log::info('[Purchase] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('purchase_invoices.index')
                ->with('success', 'Purchase invoice moved to trash.');

        } catch (\Exception $e) {
            Log::error('[Purchase] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete purchase invoice.');
        }
    }

    public function restore($id)
    {
        Purchase::onlyTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Purchase invoice restored.');
    }

    public function show($id)
    {
        $purchase = Purchase::with('items.product', 'vendor')->findOrFail($id);
        return view('purchases.show', compact('purchase'));
    }

    public function print($id)
    {
        abort(404, 'Print not yet implemented');
    }
}