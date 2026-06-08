<?php

namespace App\Http\Controllers;

use App\Models\ProjectPhase;
use App\Models\PhaseMaterial;
use App\Models\Project;
use App\Models\ServiceVendor;
use App\Models\Service;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProjectPhaseController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index($projectId)
    {
        $project = Project::findOrFail($projectId);
        $phases  = ProjectPhase::with([
                        'serviceVendor.service',
                        'serviceVendor.vendor',
                        'materials.product',
                    ])
                    ->where('project_id', $projectId)
                    ->orderBy('phase_order')
                    ->get();

        return view('phases.index', compact('project', 'phases'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function create($projectId)
    {
        $project  = Project::findOrFail($projectId);
        $services = Service::active()->with('vendors')->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        // Next phase order for this project
        $nextOrder = ProjectPhase::where('project_id', $projectId)->max('phase_order') + 1;

        return view('phases.create', compact('project', 'services', 'products', 'nextOrder'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request, $projectId)
    {
        $request->validate([
            'service_id'         => 'required|exists:services,id',
            'vendor_id'          => 'required|exists:vendors,id',
            'phase_order'        => 'required|integer|min:1',
            'rate'               => 'required|numeric|min:0',
            'notes'              => 'nullable|string|max:1000',

            // Materials (optional)
            'materials'                  => 'nullable|array',
            'materials.*.product_id'     => 'required_with:materials|exists:products,id',
            'materials.*.quantity'       => 'required_with:materials|numeric|min:0',
            'materials.*.rate'           => 'required_with:materials|numeric|min:0',
            'materials.*.notes'          => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $project = Project::findOrFail($projectId);

            // Find the service_vendor pivot record
            $serviceVendor = ServiceVendor::where('service_id', $request->service_id)
                ->where('vendor_id', $request->vendor_id)
                ->first();

            if (!$serviceVendor) {
                DB::rollBack();
                return redirect()->back()->withInput()
                    ->with('error', 'This vendor is not linked to the selected service. Please link them in the Services module first.');
            }

            $phase = ProjectPhase::create([
                'project_id'        => $projectId,
                'service_vendor_id' => $serviceVendor->id,
                'phase_order'       => $request->phase_order,
                'rate'              => $request->rate,
                'status'            => 'pending',
                'notes'             => $request->notes,
                'total_cost'        => 0,
                'created_by'        => auth()->id(),
                'updated_by'        => auth()->id(),
            ]);

            // Save materials
            if ($request->filled('materials')) {
                foreach ($request->materials as $mat) {
                    if (!empty($mat['product_id']) && isset($mat['quantity'])) {
                        $totalCost = round($mat['quantity'] * $mat['rate'], 2);
                        PhaseMaterial::create([
                            'phase_id'   => $phase->id,
                            'product_id' => $mat['product_id'],
                            'quantity'   => $mat['quantity'],
                            'rate'       => $mat['rate'],
                            'total_cost' => $totalCost,
                            'notes'      => $mat['notes'] ?? null,
                        ]);
                    }
                }
            }

            // Move project to in_production if it was po_received
            if ($project->status === 'po_received') {
                $project->update(['status' => 'in_production', 'updated_by' => auth()->id()]);
            }

            DB::commit();

            Log::info('[ProjectPhase] Created', [
                'id'         => $phase->id,
                'project_id' => $projectId,
                'by'         => auth()->id(),
            ]);

            return redirect()->route('projects.show', $projectId)
                ->with('success', 'Phase ' . $phase->phase_order . ' created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProjectPhase] Store failed', [
                'project_id' => $projectId,
                'message'    => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function show($projectId, $id)
    {
        $project = Project::findOrFail($projectId);
        $phase   = ProjectPhase::with([
                        'serviceVendor.service.unit',
                        'serviceVendor.vendor',
                        'materials.product',
                        'createdBy',
                    ])
                    ->where('project_id', $projectId)
                    ->findOrFail($id);

        return view('phases.show', compact('project', 'phase'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function edit($projectId, $id)
    {
        $project  = Project::findOrFail($projectId);
        $phase    = ProjectPhase::with('materials')
                        ->where('project_id', $projectId)
                        ->findOrFail($id);

        // Cannot edit dispatched or further phases
        if (in_array($phase->status, ['approved', 'rejected'])) {
            return redirect()->route('projects.phases.show', [$projectId, $id])
                ->with('error', 'Cannot edit an ' . $phase->getStatusLabel() . ' phase.');
        }

        $services = Service::active()->with('vendors')->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return view('phases.edit', compact('project', 'phase', 'services', 'products'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $projectId, $id)
    {
        $request->validate([
            'service_id'             => 'required|exists:services,id',
            'vendor_id'              => 'required|exists:vendors,id',
            'phase_order'            => 'required|integer|min:1',
            'rate'                   => 'required|numeric|min:0',
            'notes'                  => 'nullable|string|max:1000',
            'materials'              => 'nullable|array',
            'materials.*.product_id' => 'required_with:materials|exists:products,id',
            'materials.*.quantity'   => 'required_with:materials|numeric|min:0',
            'materials.*.rate'       => 'required_with:materials|numeric|min:0',
            'materials.*.notes'      => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $phase = ProjectPhase::where('project_id', $projectId)->findOrFail($id);

            $serviceVendor = ServiceVendor::where('service_id', $request->service_id)
                ->where('vendor_id', $request->vendor_id)
                ->first();

            if (!$serviceVendor) {
                DB::rollBack();
                return redirect()->back()->withInput()
                    ->with('error', 'This vendor is not linked to the selected service.');
            }

            $phase->update([
                'service_vendor_id' => $serviceVendor->id,
                'phase_order'       => $request->phase_order,
                'rate'              => $request->rate,
                'notes'             => $request->notes,
                'updated_by'        => auth()->id(),
            ]);

            // Replace materials
            $phase->materials()->delete();
            if ($request->filled('materials')) {
                foreach ($request->materials as $mat) {
                    if (!empty($mat['product_id']) && isset($mat['quantity'])) {
                        PhaseMaterial::create([
                            'phase_id'   => $phase->id,
                            'product_id' => $mat['product_id'],
                            'quantity'   => $mat['quantity'],
                            'rate'       => $mat['rate'],
                            'total_cost' => round($mat['quantity'] * $mat['rate'], 2),
                            'notes'      => $mat['notes'] ?? null,
                        ]);
                    }
                }
            }

            $phase->recalcCost();

            DB::commit();

            Log::info('[ProjectPhase] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('projects.phases.show', [$projectId, $id])
                ->with('success', 'Phase updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProjectPhase] Update failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /projects/{project}/phases/{id}/dispatch
    public function dispatch(Request $request, $projectId, $id)
    {
        $request->validate([
            'quantity_dispatched' => 'required|numeric|min:0.001',
            'dispatched_at'       => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            $phase = ProjectPhase::where('project_id', $projectId)
                ->whereIn('status', ['pending'])
                ->findOrFail($id);

            $phase->update([
                'quantity_dispatched' => $request->quantity_dispatched,
                'dispatched_at'       => $request->dispatched_at,
                'status'              => 'dispatched',
                'updated_by'          => auth()->id(),
            ]);

            DB::commit();

            Log::info('[ProjectPhase] Dispatched', [
                'id'  => $id,
                'qty' => $request->quantity_dispatched,
                'by'  => auth()->id(),
            ]);

            return redirect()->route('projects.phases.show', [$projectId, $id])
                ->with('success', 'Phase dispatched successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProjectPhase] Dispatch failed', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Could not dispatch phase. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /projects/{project}/phases/{id}/receive
    public function receive(Request $request, $projectId, $id)
    {
        $request->validate([
            'quantity_received' => 'required|numeric|min:0',
            'quantity_rejected' => 'nullable|numeric|min:0',
            'received_at'       => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            $phase = ProjectPhase::where('project_id', $projectId)
                ->whereIn('status', ['dispatched', 'partially_received'])
                ->findOrFail($id);

            $received = (float) $request->quantity_received;
            $rejected = (float) ($request->quantity_rejected ?? 0);
            $totalAccountedFor = $received + $rejected
                + (float) $phase->quantity_received
                + (float) $phase->quantity_rejected;

            // Determine new status
            if ($totalAccountedFor >= (float) $phase->quantity_dispatched) {
                $newStatus = 'fully_received';
            } else {
                $newStatus = 'partially_received';
            }

            // Accumulate — don't overwrite previous receipts
            $newReceived = (float) $phase->quantity_received + $received;
            $newRejected = (float) $phase->quantity_rejected + $rejected;

            $phase->update([
                'quantity_received' => $newReceived,
                'quantity_rejected' => $newRejected,
                'received_at'       => $request->received_at,
                'status'            => $newStatus,
                'updated_by'        => auth()->id(),
            ]);

            $phase->recalcCost();

            DB::commit();

            Log::info('[ProjectPhase] Received', [
                'id'       => $id,
                'received' => $received,
                'rejected' => $rejected,
                'status'   => $newStatus,
                'by'       => auth()->id(),
            ]);

            return redirect()->route('projects.phases.show', [$projectId, $id])
                ->with('success', 'Receipt recorded. Status: ' . ProjectPhase::STATUSES[$newStatus]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProjectPhase] Receive failed', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Could not record receipt. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // PATCH /projects/{project}/phases/{id}/status
    // Used for: fully_received → approved / rejected
    public function updateStatus(Request $request, $projectId, $id)
    {
        $request->validate([
            'status'           => ['required', Rule::in(['approved', 'rejected'])],
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $phase = ProjectPhase::where('project_id', $projectId)
                ->whereIn('status', ['fully_received', 'partially_received'])
                ->findOrFail($id);

            $phase->update([
                'status'           => $request->status,
                'rejection_reason' => $request->rejection_reason,
                'updated_by'       => auth()->id(),
            ]);

            DB::commit();

            Log::info('[ProjectPhase] Status updated', [
                'id'     => $id,
                'status' => $request->status,
                'by'     => auth()->id(),
            ]);

            return redirect()->route('projects.phases.show', [$projectId, $id])
                ->with('success', 'Phase marked as ' . ucfirst($request->status) . '.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProjectPhase] Status update failed', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Could not update status.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($projectId, $id)
    {
        DB::beginTransaction();
        try {
            $phase = ProjectPhase::where('project_id', $projectId)->findOrFail($id);

            if (in_array($phase->status, ['dispatched', 'partially_received', 'fully_received', 'approved'])) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Cannot delete a phase that has been dispatched or received.');
            }

            $phase->materials()->delete();
            $phase->delete();

            DB::commit();

            Log::info('[ProjectPhase] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('projects.show', $projectId)
                ->with('success', 'Phase deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[ProjectPhase] Destroy failed', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Could not delete phase.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX: get phase items for a project phase (used in sale invoice)
    public function getItems($phaseId)
    {
        try {
            $phase = ProjectPhase::with([
                'serviceVendor.service',
                'serviceVendor.vendor',
                'materials.product',
            ])->findOrFail($phaseId);

            return response()->json([
                'success'   => true,
                'phase'     => [
                    'id'                  => $phase->id,
                    'phase_order'         => $phase->phase_order,
                    'service'             => optional(optional($phase->serviceVendor)->service)->name,
                    'vendor'              => optional(optional($phase->serviceVendor)->vendor)->name,
                    'rate'                => $phase->rate,
                    'quantity_dispatched' => $phase->quantity_dispatched,
                    'quantity_received'   => $phase->quantity_received,
                    'total_cost'          => $phase->total_cost,
                    'status'              => $phase->status,
                ],
                'materials' => $phase->materials->map(fn($m) => [
                    'id'         => $m->id,
                    'product'    => optional($m->product)->name,
                    'quantity'   => $m->quantity,
                    'rate'       => $m->rate,
                    'total_cost' => $m->total_cost,
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('[ProjectPhase] getItems failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX: get vendors for a service (used in create/edit phase form)
    // Route: helpers.service.vendors
    public function getVendorsForService(Request $request, $serviceId)
    {
        try {
            $rows = ServiceVendor::with('vendor')
                ->where('service_id', $serviceId)
                ->get()
                ->map(fn($sv) => [
                    'id'        => $sv->vendor_id,
                    'name'      => optional($sv->vendor)->name,
                    'rate'      => $sv->rate,
                    'currency'  => $sv->currency,
                    'sv_id'     => $sv->id,
                ]);

            return response()->json(['success' => true, 'vendors' => $rows]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'vendors' => []], 500);
        }
    }
}