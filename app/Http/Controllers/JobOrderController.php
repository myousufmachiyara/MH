<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Models\Vendor;
use App\Models\Product;
use App\Models\VendorStockLedger;
use App\Services\JobOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JobOrderController extends Controller
{
    public function __construct(private JobOrderService $service) {}

    public function index(Request $request)
    {
        $jobOrders = JobOrder::with('vendor', 'items.product')
            ->when($request->boolean('view_deleted'), fn($q) => $q->onlyTrashed())
            ->orderByDesc('issue_date')
            ->get();

        return view('job_orders.index', compact('jobOrders'));
    }

    public function create()
    {
        $vendors  = Vendor::active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();

        return view('job_orders.create', compact('vendors', 'products'));
    }

    // AJAX: available stock (fresh+leftover) for a vendor+product, for live validation
    public function availableStock(Request $request)
    {
        $vendorId  = $request->vendor_id;
        $productId = $request->product_id;

        $fresh    = VendorStockLedger::balance($vendorId, $productId, 'fresh');
        $leftover = VendorStockLedger::balance($vendorId, $productId, 'leftover');

        return response()->json([
            'fresh'    => $fresh,
            'leftover' => $leftover,
            'total'    => $fresh + $leftover,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id'          => 'required|exists:vendors,id',
            'sale_id'            => 'nullable|exists:sales,id',
            'job_type'           => 'nullable|string|max:50',
            'issue_date'         => 'required|date',
            'remarks'            => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
        ]);

        try {
            $jobOrder = $this->service->create([
                'vendor_id'  => $request->vendor_id,
                'sale_id'    => $request->sale_id,
                'job_type'   => $request->job_type,
                'issue_date' => $request->issue_date,
                'remarks'    => $request->remarks,
            ], $request->items, auth()->id());

            Log::info('[JobOrder] Created', ['id' => $jobOrder->id, 'by' => auth()->id()]);

            return redirect()->route('jobs.index')
                ->with('success', 'Job order ' . $jobOrder->job_no . ' issued successfully.');

        } catch (\Exception $e) {
            Log::error('[JobOrder] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $jobOrder = JobOrder::with('items.product', 'vendor', 'receives')->findOrFail($id);
        return view('job_orders.show', compact('jobOrder'));
    }

    public function destroy($id)
    {
        try {
            $jobOrder = JobOrder::findOrFail($id);
            $this->service->delete($jobOrder);

            return redirect()->route('jobs.index')
                ->with('success', 'Job order deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[JobOrder] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function print($id)
    {
        abort(404, 'Print not yet implemented');
    }
}