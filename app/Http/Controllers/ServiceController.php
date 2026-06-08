<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Vendor;
use App\Models\MeasurementUnit;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $services = Service::with(['unit', 'expenseAccount', 'vendors'])
                           ->orderBy('name')
                           ->get();

        $units           = MeasurementUnit::orderBy('name')->get();
        $expenseAccounts = ChartOfAccounts::whereIn('account_type', [
                                'service_cost', 'expenses', 'cogs',
                                'freight', 'sampling', 'packaging',
                           ])->orderBy('name')->get();
        $vendors         = Vendor::active()->orderBy('name')->get();

        return view('projects.services', compact(
            'services', 'units', 'expenseAccounts', 'vendors'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name'               => 'required|string|max:255|unique:services,name',
            'description'        => 'nullable|string|max:1000',
            'unit_id'            => 'nullable|exists:measurement_units,id',
            'expense_account_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active'          => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $service = Service::create([
                'name'               => $request->name,
                'description'        => $request->description,
                'unit_id'            => $request->unit_id,
                'expense_account_id' => $request->expense_account_id,
                'is_active'          => $request->boolean('is_active', true),
                'created_by'         => auth()->id(),
                'updated_by'         => auth()->id(),
            ]);

            DB::commit();
            Log::info('[Service] Created', ['id' => $service->id, 'by' => auth()->id()]);

            return redirect()->route('services.index')
                ->with('success', 'Service "' . $service->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Service] Store failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Returns JSON for edit modal — includes vendor pivot data
    public function edit($id)
    {
        try {
            $service = Service::with([
                'unit',
                'expenseAccount',
                'vendors' => fn($q) => $q->select('vendors.id', 'vendors.name', 'vendors.vendor_type')
                                         ->orderBy('vendors.name'),
            ])->findOrFail($id);

            return response()->json([
                'id'                 => $service->id,
                'name'               => $service->name,
                'description'        => $service->description,
                'unit_id'            => $service->unit_id,
                'expense_account_id' => $service->expense_account_id,
                'is_active'          => $service->is_active,
                'vendors'            => $service->vendors->map(fn($v) => [
                    'id'       => $v->id,
                    'name'     => $v->name,
                    'type'     => $v->getTypeLabel(),
                    'rate'     => $v->pivot->rate,
                    'currency' => $v->pivot->currency,
                    'notes'    => $v->pivot->notes,
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Service not found.'], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'               => ['required', 'string', 'max:255',
                                     Rule::unique('services', 'name')->ignore($id)],
            'description'        => 'nullable|string|max:1000',
            'unit_id'            => 'nullable|exists:measurement_units,id',
            'expense_account_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active'          => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $service = Service::findOrFail($id);

            $service->update([
                'name'               => $request->name,
                'description'        => $request->description,
                'unit_id'            => $request->unit_id,
                'expense_account_id' => $request->expense_account_id,
                'is_active'          => $request->boolean('is_active', $service->is_active),
                'updated_by'         => auth()->id(),
            ]);

            DB::commit();
            Log::info('[Service] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('services.index')
                ->with('success', 'Service updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Service] Update failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $service = Service::findOrFail($id);

            // Guard: cannot delete if service is used in project phases
            $inUse = DB::table('project_phases')
                ->where('service_id', $id)
                ->exists();

            if ($inUse) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Cannot delete "' . $service->name . '" — it is used in project phases.');
            }

            // Detach all vendors from pivot before soft-deleting
            $service->vendors()->detach();
            $service->delete();

            DB::commit();
            Log::info('[Service] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('services.index')
                ->with('success', 'Service deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Service] Destroy failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
            ]);
            return redirect()->back()
                ->with('error', 'Could not delete service. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // ── Vendor Pivot Management ──────────────────────────────────────
    // Routes: services.vendors.attach / update / detach
    // All return JSON — called via fetch() from the blade JS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Attach a vendor to a service with rate.
     * POST /services/{service}/vendors
     */
    public function attachVendor(Request $request, $serviceId)
    {
        $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'rate'      => 'required|numeric|min:0',
            'currency'  => 'nullable|string|max:10',
            'notes'     => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $service = Service::findOrFail($serviceId);

            // Check not already attached
            if ($service->vendors()->where('vendor_id', $request->vendor_id)->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This vendor is already linked to this service.',
                ], 422);
            }

            $service->vendors()->attach($request->vendor_id, [
                'rate'     => $request->rate,
                'currency' => $request->currency ?? 'PKR',
                'notes'    => $request->notes,
            ]);

            DB::commit();

            $vendor = Vendor::find($request->vendor_id);
            Log::info('[Service] Vendor attached', [
                'service_id' => $serviceId,
                'vendor_id'  => $request->vendor_id,
                'rate'       => $request->rate,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vendor linked successfully.',
                'vendor'  => [
                    'id'       => $vendor->id,
                    'name'     => $vendor->name,
                    'type'     => $vendor->getTypeLabel(),
                    'rate'     => $request->rate,
                    'currency' => $request->currency ?? 'PKR',
                    'notes'    => $request->notes,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Service] attachVendor failed', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    /**
     * Update the pivot rate/notes for an existing vendor link.
     * PUT /services/{service}/vendors/{vendor}
     */
    public function updateVendor(Request $request, $serviceId, $vendorId)
    {
        $request->validate([
            'rate'     => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'notes'    => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $service = Service::findOrFail($serviceId);

            $service->vendors()->updateExistingPivot($vendorId, [
                'rate'     => $request->rate,
                'currency' => $request->currency ?? 'PKR',
                'notes'    => $request->notes,
            ]);

            DB::commit();
            Log::info('[Service] Vendor pivot updated', [
                'service_id' => $serviceId,
                'vendor_id'  => $vendorId,
                'rate'       => $request->rate,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vendor rate updated successfully.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Service] updateVendor failed', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    /**
     * Detach a vendor from a service.
     * DELETE /services/{service}/vendors/{vendor}
     */
    public function detachVendor(Request $request, $serviceId, $vendorId)
    {
        DB::beginTransaction();
        try {
            $service = Service::findOrFail($serviceId);
            $service->vendors()->detach($vendorId);

            DB::commit();
            Log::info('[Service] Vendor detached', [
                'service_id' => $serviceId,
                'vendor_id'  => $vendorId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vendor removed from service.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Service] detachVendor failed', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX: get vendors for a given service (used in project phase assignment)
    // Route: helpers.service.vendors  GET /helpers/services/{service}/vendors
    public function getVendors($serviceId)
    {
        try {
            $service = Service::with('vendors')->findOrFail($serviceId);

            $vendors = $service->vendors->map(fn($v) => [
                'id'       => $v->id,
                'name'     => $v->name,
                'type'     => $v->getTypeLabel(),
                'rate'     => $v->pivot->rate,
                'currency' => $v->pivot->currency,
            ]);

            return response()->json([
                'success' => true,
                'vendors' => $vendors,
                'service' => [
                    'id'      => $service->id,
                    'name'    => $service->name,
                    'unit'    => optional($service->unit)->shortcode,
                    'unit_id' => $service->unit_id,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('[Service] getVendors failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'vendors' => []], 404);
        }
    }
}