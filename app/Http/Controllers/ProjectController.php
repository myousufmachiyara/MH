<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        // FIX: only eager load relationships that EXIST right now.
        // 'phases', 'samples', 'purchaseOrders', 'saleInvoices' are
        // commented out in Project.php until their modules are installed.
        // Eager loading a non-existent relationship throws "Class not found".
        $projects = Project::with(['customer'])
                           ->latest()
                           ->get();

        return view('projects.index', compact('projects'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function create()
    {
        $customers = Customer::active()->orderBy('name')->get();
        return view('projects.create', compact('customers'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'customer_id'    => 'required|exists:customers,id',
            'title'          => 'required|string|max:500',
            'customer_po_no' => 'nullable|string|max:100',
            'order_date'     => 'nullable|date',
            'delivery_date'  => 'nullable|date|after_or_equal:order_date',
            'notes'          => 'nullable|string|max:2000',
        ]);

        DB::beginTransaction();
        try {
            $projectNo = Project::generateProjectNo();

            $project = Project::create([
                'project_no'     => $projectNo,
                'customer_id'    => $request->customer_id,
                'title'          => $request->title,
                'customer_po_no' => $request->customer_po_no,
                'status'         => 'sampling',
                'order_date'     => $request->order_date,
                'delivery_date'  => $request->delivery_date,
                'notes'          => $request->notes,
                'created_by'     => auth()->id(),
                'updated_by'     => auth()->id(),
            ]);

            DB::commit();

            Log::info('[Project] Created', [
                'id'         => $project->id,
                'project_no' => $project->project_no,
                'by'         => auth()->id(),
            ]);

            return redirect()->route('projects.show', $project->id)
                ->with('success', 'Project ' . $project->project_no . ' created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Project] Store failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function show($id)
    {
        // FIX: only load relationships that are currently active in Project.php.
        // Uncomment each relationship here as the corresponding module is installed:
        //   'samples.costs'                    → after Sampling module
        //   'phases.serviceVendor.service' etc → after Project Phases module
        //   'purchaseOrders.vendor'            → after Purchase Orders module
        //   'saleInvoices'                     → after Sale Invoices module
        $project = Project::with([
            'customer',
            'comments.user',
            'samples.costs',
            'phases.serviceVendor.service.unit',
            'phases.serviceVendor.vendor',
            'phases.materials.product',
            'createdBy',
        ])->findOrFail($id);

        return view('projects.show', compact('project'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function edit($id)
    {
        $project   = Project::findOrFail($id);
        $customers = Customer::active()->orderBy('name')->get();
        return view('projects.edit', compact('project', 'customers'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $request->validate([
            'customer_id'    => 'required|exists:customers,id',
            'title'          => 'required|string|max:500',
            'customer_po_no' => 'nullable|string|max:100',
            'order_date'     => 'nullable|date',
            'delivery_date'  => 'nullable|date',
            'notes'          => 'nullable|string|max:2000',
        ]);

        DB::beginTransaction();
        try {
            $project = Project::findOrFail($id);

            $project->update([
                'customer_id'    => $request->customer_id,
                'title'          => $request->title,
                'customer_po_no' => $request->customer_po_no,
                'order_date'     => $request->order_date,
                'delivery_date'  => $request->delivery_date,
                'notes'          => $request->notes,
                'updated_by'     => auth()->id(),
            ]);

            DB::commit();

            Log::info('[Project] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('projects.show', $id)
                ->with('success', 'Project updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Project] Update failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
        ]);

        DB::beginTransaction();
        try {
            $project = Project::findOrFail($id);

            $old = $project->status;
            $project->update([
                'status'     => $request->status,
                'updated_by' => auth()->id(),
            ]);

            DB::commit();

            Log::info('[Project] Status changed', [
                'id'   => $id,
                'from' => $old,
                'to'   => $request->status,
                'by'   => auth()->id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'status'  => $request->status,
                    'label'   => $project->getStatusLabel(),
                    'badge'   => $project->getStatusBadge(),
                ]);
            }

            return redirect()->route('projects.show', $id)
                ->with('success', 'Project status updated to ' . $project->getStatusLabel() . '.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Project] Status update failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Update failed.'], 500);
            }
            return redirect()->back()->with('error', 'Could not update status.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $project = Project::findOrFail($id);

            if (in_array($project->status, ['completed', 'in_production'])) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Cannot delete a project that is in production or completed.');
            }

            $project->delete();

            DB::commit();

            Log::info('[Project] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('projects.index')
                ->with('success', 'Project deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Project] Destroy failed', ['message' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Could not delete project.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX: get accumulated costing — only works after Phases + Sampling installed
    public function getCosting($id)
    {
        try {
            // Load only what currently exists
            $project = Project::with(['customer'])->findOrFail($id);

            // These will be populated once modules are installed:
            // 'phases.serviceVendor.service', 'phases.materials', 'samples.costs'

            return response()->json([
                'success'    => true,
                'project_no' => $project->project_no,
                'customer'   => optional($project->customer)->name,
                'line_items' => [],    // populated after Phases + Sampling installed
                'total_cost' => 0,
            ]);

        } catch (\Exception $e) {
            Log::error('[Project] getCosting failed', ['id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not load costing.'], 500);
        }
    }
}