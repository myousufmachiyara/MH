<?php

namespace App\Http\Controllers;

use App\Models\JobOrder;
use App\Models\JobOrderReceive;
use App\Models\VendorStockLedger;
use App\Models\Product;
use App\Services\JobOrderReceiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JobOrderReceiveController extends Controller
{
    public function __construct(private JobOrderReceiveService $service) {}

    public function index(Request $request)
    {
        $receives = JobOrderReceive::with('jobOrder.vendor', 'items')
            ->when($request->boolean('view_deleted'), fn($q) => $q->onlyTrashed())
            ->orderByDesc('receive_date')
            ->get();

        return view('job_receives.index', compact('receives'));
    }

    public function create(Request $request)
    {
        $jobOrders = JobOrder::with('vendor', 'items.product')
            ->whereIn('status', ['Issued', 'PartiallyReceived'])
            ->orderByDesc('issue_date')
            ->get();

        $products = Product::active()->orderBy('name')->get();

        return view('job_receives.create', compact('jobOrders', 'products'));
    }

    // AJAX: outstanding (still-issued) raw quantities for a job order —
    // used to populate the product dropdown + show available qty per row
    public function outstanding($jobOrderId)
    {
        $jobOrder = JobOrder::with('vendor')->findOrFail($jobOrderId);

        $issued = VendorStockLedger::where('vendor_id', $jobOrder->vendor_id)
            ->where('reference_type', 'JobOrder')
            ->where('reference_id', $jobOrder->id)
            ->where('status', 'issued')
            ->selectRaw('product_id, SUM(quantity) as issued_qty')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $received = VendorStockLedger::where('vendor_id', $jobOrder->vendor_id)
            ->whereIn('reference_id', $jobOrder->receives()->pluck('id'))
            ->where('reference_type', 'JobOrderReceive')
            ->where('status', 'issued')
            ->selectRaw('product_id, SUM(quantity) as received_qty')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $outstanding = [];
        foreach ($issued as $productId => $row) {
            $stillOutstanding = (float) $row->issued_qty + (float) ($received[$productId]->received_qty ?? 0);
            if ($stillOutstanding > 0.001) {
                $product = Product::find($productId);
                $outstanding[] = [
                    'product_id'   => $productId,
                    'product_name' => $product?->name,
                    'outstanding'  => round($stillOutstanding, 3),
                ];
            }
        }

        return response()->json($outstanding);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'job_order_id'                       => 'required|exists:job_orders,id',
            'receive_date'                        => 'required|date',
            'processing_charge_override'          => 'nullable|numeric|min:0',
            'remarks'                             => 'nullable|string',
            'items'                                => 'required|array|min:1',
            'items.*.raw_product_id'              => 'required|integer|exists:products,id',
            'items.*.quantity_consumed'           => 'required|numeric|min:0',
            'items.*.output_product_id'           => 'nullable|integer|exists:products,id',
            'items.*.quantity_output'             => 'nullable|numeric|min:0',
            'items.*.conversion_rate'             => 'nullable|numeric|min:0',
        ]);

        $items = array_values($validated['items']);
        $items = array_values(array_filter($items, function ($item) {
            return !empty($item['raw_product_id'])
                && ((float) ($item['quantity_consumed'] ?? 0) > 0 || (float) ($item['quantity_output'] ?? 0) > 0);
        }));

        if (empty($items)) {
            return back()->withInput()->with('error', 'Enter at least one item with a quantity consumed or output.');
        }

        try {
            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('job_receive_attachments', 'public');
                }
            }

            $receive = $this->service->create([
                'job_order_id'                => $validated['job_order_id'],
                'receive_date'                => $validated['receive_date'],
                'processing_charge_override'  => $validated['processing_charge_override'] ?? null,
                'remarks'                     => $validated['remarks'] ?? null,
                'attachments'                 => $attachments ?: null,
            ], $items, auth()->id());

            Log::info('[JobOrderReceive] Created', ['id' => $receive->id, 'items_count' => count($items), 'by' => auth()->id()]);

            return redirect()->route('job_receives.index')
                ->with('success', 'Job receive ' . $receive->receive_no . ' recorded successfully.');

        } catch (\Exception $e) {
            Log::error('[JobOrderReceive] Store failed', ['message' => $e->getMessage(), 'items' => $items]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'receive_date'                        => 'required|date',
            'processing_charge_override'          => 'nullable|numeric|min:0',
            'remarks'                             => 'nullable|string',
            'items'                                => 'required|array|min:1',
            'items.*.raw_product_id'              => 'required|integer|exists:products,id',
            'items.*.quantity_consumed'           => 'required|numeric|min:0',
            'items.*.output_product_id'           => 'nullable|integer|exists:products,id',
            'items.*.quantity_output'             => 'nullable|numeric|min:0',
            'items.*.conversion_rate'             => 'nullable|numeric|min:0',
        ]);

        $items = array_values(array_filter($validated['items'], function ($item) {
            return !empty($item['raw_product_id'])
                && ((float) ($item['quantity_consumed'] ?? 0) > 0 || (float) ($item['quantity_output'] ?? 0) > 0);
        }));

        if (empty($items)) {
            return back()->withInput()->with('error', 'Enter at least one item.');
        }

        try {
            $receive = JobOrderReceive::findOrFail($id);

            $attachments = $receive->attachments ?? [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('job_receive_attachments', 'public');
                }
            }

            $this->service->update($receive, [
                'receive_date'               => $validated['receive_date'],
                'processing_charge_override' => $validated['processing_charge_override'] ?? null,
                'remarks'                    => $validated['remarks'] ?? null,
                'attachments'                => $attachments ?: null,
            ], $items, auth()->id());

            Log::info('[JobOrderReceive] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('job_receives.index')
                ->with('success', 'Job receive updated successfully.');

        } catch (\Exception $e) {
            Log::error('[JobOrderReceive] Update failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
    
    public function edit($id)
    {
        $receive = JobOrderReceive::with('items.rawProduct', 'items.outputProduct', 'jobOrder.vendor')
            ->findOrFail($id);

        $products = Product::active()->orderBy('name')->get();

        return view('job_receives.edit', compact('receive', 'products'));
    }
    
    public function show($id)
    {
        $receive = JobOrderReceive::with('items.rawProduct', 'items.outputProduct', 'jobOrder.vendor')
            ->findOrFail($id);

        return view('job_receives.show', compact('receive'));
    }

    public function destroy($id)
    {
        try {
            $receive = JobOrderReceive::findOrFail($id);
            $this->service->delete($receive);

            return redirect()->route('job_receives.index')
                ->with('success', 'Job receive deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[JobOrderReceive] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete job receive.');
        }
    }

    public function print($id)
    {
        abort(404, 'Print not yet implemented');
    }
}