<?php

namespace App\Http\Controllers;

use App\Models\QualityCheck;
use App\Services\QualityCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QualityCheckController extends Controller
{
    public function __construct(private QualityCheckService $service) {}

    public function index(Request $request)
    {
        $qcs = QualityCheck::with('jobOrderReceive.jobOrder.vendor', 'product')
            ->when($request->boolean('view_deleted'), fn($q) => $q->onlyTrashed())
            ->orderByDesc('qc_date')
            ->get();

        return view('quality_checks.index', compact('qcs'));
    }

    public function create()
    {
        $pending = $this->service->pendingReceiveOutputs();
        return view('quality_checks.create', compact('pending'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'job_order_receive_id' => 'required|exists:job_order_receives,id',
            'product_id'           => 'required|exists:products,id',
            'quantity_inspected'   => 'required|numeric|min:0.001',
            'quantity_passed'      => 'required|numeric|min:0',
            'rejection_reason'     => 'nullable|string|max:255',
            'qc_date'              => 'required|date',
            'remarks'              => 'nullable|string',
        ]);

        try {
            $qc = $this->service->create($request->all(), auth()->id());

            Log::info('[QualityCheck] Created', ['id' => $qc->id, 'by' => auth()->id()]);

            return redirect()->route('quality_checks.index')
                ->with('success', 'QC ' . $qc->qc_no . ' recorded successfully.');

        } catch (\Exception $e) {
            Log::error('[QualityCheck] Store failed', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $qc = QualityCheck::findOrFail($id);
            $this->service->delete($qc);

            return redirect()->route('quality_checks.index')
                ->with('success', 'QC record deleted.');

        } catch (\Exception $e) {
            Log::error('[QualityCheck] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete QC record.');
        }
    }

    public function print($id)
    {
        abort(404, 'Print not yet implemented');
    }
}