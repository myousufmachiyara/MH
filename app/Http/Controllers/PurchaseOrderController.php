<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseOrderService $service) {}

    public function index(Request $request)
    {
        $orders = PurchaseOrder::with('vendor')
            ->when($request->boolean('view_deleted'), fn($q) => $q->onlyTrashed())
            ->orderByDesc('order_date')
            ->get();

        return view('purchase_orders.index', compact('orders'));
    }

    public function create()
    {
        $vendors  = Vendor::active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();
        $units    = MeasurementUnit::orderBy('name')->get();

        return view('purchase_orders.create', compact('vendors', 'products', 'units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'order_date'              => 'required|date',
            'vendor_id'               => 'required|exists:vendors,id',
            'expected_date'           => 'nullable|date',
            'remarks'                 => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.item_id'         => 'required|exists:products,id',
            'items.*.quantity'        => 'required|numeric|min:0.001',
        ]);

        try {
            $items = collect($request->items)->map(fn($i) => [
                'product_id'      => $i['item_id'],
                'quantity'        => $i['quantity'],
                'estimated_price' => $i['price'] ?? 0,
            ])->toArray();

            $order = $this->service->create([
                'vendor_id'     => $request->vendor_id,
                'order_date'    => $request->order_date,
                'expected_date' => $request->expected_date,
                'remarks'       => $request->remarks,
            ], $items, auth()->id());

            Log::info('[PurchaseOrder] Created', ['id' => $order->id, 'by' => auth()->id()]);

            return redirect()->route('purchase_orders.index')
                ->with('success', 'Purchase order ' . $order->order_no . ' created successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseOrder] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $order = PurchaseOrder::findOrFail($id);
            $this->service->delete($order);

            return redirect()->route('purchase_orders.index')
                ->with('success', 'Purchase order deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseOrder] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function edit($id)
    {
        $order    = PurchaseOrder::with('items.product')->findOrFail($id);
        $vendors  = Vendor::active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();
        $units    = MeasurementUnit::orderBy('name')->get();

        return view('purchase_orders.edit', compact('order', 'vendors', 'products', 'units'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'order_date'       => 'required|date',
            'vendor_id'        => 'required|exists:vendors,id',
            'expected_date'    => 'nullable|date',
            'items'            => 'required|array|min:1',
            'items.*.item_id'  => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ]);

        try {
            $order = PurchaseOrder::findOrFail($id);

            if ($order->status === 'Converted') {
                return back()->with('error', 'Cannot edit — this order has already been converted to an invoice.');
            }

            $items = collect($request->items)->map(fn($i) => [
                'product_id'      => $i['item_id'],
                'quantity'        => $i['quantity'],
                'estimated_price' => $i['price'] ?? 0,
            ])->toArray();

            $this->service->update($order, [
                'vendor_id'     => $request->vendor_id,
                'order_date'    => $request->order_date,
                'expected_date' => $request->expected_date,
                'remarks'       => $request->remarks,
            ], $items, auth()->id());

            Log::info('[PurchaseOrder] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('purchase_orders.index')
                ->with('success', 'Purchase order updated successfully.');

        } catch (\Exception $e) {
            Log::error('[PurchaseOrder] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function restore($id)
    {
        PurchaseOrder::onlyTrashed()->findOrFail($id)->restore();
        return back()->with('success', 'Purchase order restored.');
    }

    public function print($id)
    {
        abort(404, 'Print not yet implemented');
    }
}