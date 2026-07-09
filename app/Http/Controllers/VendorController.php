<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::orderBy('name')->get();
        return view('accounts.vendors', compact('vendors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                 => 'required|string|max:255',
            'vendor_type'          => ['required', Rule::in(array_keys(Vendor::TYPES))],
            'phone'                => 'nullable|string|max:50',
            'email'                => 'nullable|email|max:255',
            'contact_person'       => 'nullable|string|max:255',
            'address'              => 'nullable|string|max:500',
            'city'                 => 'nullable|string|max:100',
            'ntn'                  => 'nullable|string|max:50',
            'opening_balance'      => 'nullable|numeric|min:0',
            'opening_type'         => 'nullable|in:receivable,payable',
            'opening_balance_date' => 'nullable|date',
            'notes'                => 'nullable|string|max:1000',
            'is_active'            => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $vendor = Vendor::create([
                'name'                 => $request->name,
                'vendor_type'          => $request->vendor_type,
                'phone'                => $request->phone,
                'email'                => $request->email,
                'contact_person'       => $request->contact_person,
                'address'              => $request->address,
                'city'                 => $request->city,
                'ntn'                  => $request->ntn,
                'opening_balance'      => $request->opening_balance ?? 0,
                'opening_type'         => $request->opening_type ?? 'payable',
                'opening_balance_date' => $request->opening_balance_date ?? now()->toDateString(),
                'notes'                => $request->notes,
                'is_active'            => $request->boolean('is_active', true),
                'created_by'           => auth()->id(),
                'updated_by'           => auth()->id(),
            ]);
            // No COA/observer side effects — vendors are separate from COA.
            // opening_balance + opening_type feed the balance formula directly.

            DB::commit();

            Log::info('[Vendor] Created', ['id' => $vendor->id, 'by' => auth()->id()]);

            return redirect()->route('vendors.index')
                ->with('success', 'Vendor "' . $vendor->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Vendor] Store failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function edit($id)
    {
        try {
            $vendor = Vendor::findOrFail($id);
            return response()->json($vendor);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Vendor not found.'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'                 => 'required|string|max:255',
            'vendor_type'          => ['required', Rule::in(array_keys(Vendor::TYPES))],
            'phone'                => 'nullable|string|max:50',
            'email'                => 'nullable|email|max:255',
            'contact_person'       => 'nullable|string|max:255',
            'address'              => 'nullable|string|max:500',
            'city'                 => 'nullable|string|max:100',
            'ntn'                  => 'nullable|string|max:50',
            'opening_balance'      => 'nullable|numeric|min:0',
            'opening_type'         => 'nullable|in:receivable,payable',
            'opening_balance_date' => 'nullable|date',
            'notes'                => 'nullable|string|max:1000',
            'is_active'            => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $vendor = Vendor::findOrFail($id);

            $vendor->update([
                'name'                 => $request->name,
                'vendor_type'          => $request->vendor_type,
                'phone'                => $request->phone,
                'email'                => $request->email,
                'contact_person'       => $request->contact_person,
                'address'              => $request->address,
                'city'                 => $request->city,
                'ntn'                  => $request->ntn,
                'opening_balance'      => $request->opening_balance ?? $vendor->opening_balance,
                'opening_type'         => $request->opening_type ?? $vendor->opening_type,
                'opening_balance_date' => $request->opening_balance_date ?? $vendor->opening_balance_date,
                'notes'                => $request->notes,
                'is_active'            => $request->boolean('is_active', $vendor->is_active),
                'updated_by'           => auth()->id(),
            ]);

            DB::commit();

            Log::info('[Vendor] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('vendors.index')
                ->with('success', 'Vendor updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Vendor] Update failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $vendor = Vendor::findOrFail($id);

            // Guard: cannot delete if vendor has any voucher entries (transaction history)
            $hasEntries = DB::table('voucher_entries')
                ->where('party_type', 'vendor')
                ->where('party_id', $id)
                ->exists();

            // Guard: cannot delete if vendor has purchases or job orders
            $hasPurchases = DB::table('purchases')->where('vendor_id', $id)->exists();
            $hasJobOrders = DB::table('job_orders')->where('vendor_id', $id)->exists();

            if ($hasEntries || $hasPurchases || $hasJobOrders) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Cannot delete "' . $vendor->name . '" — it has transaction history. Deactivate instead.');
            }

            $vendor->delete();

            DB::commit();

            Log::info('[Vendor] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('vendors.index')
                ->with('success', 'Vendor deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Vendor] Destroy failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
            ]);
            return redirect()->back()
                ->with('error', 'Could not delete vendor. Please try again.');
        }
    }

    // AJAX: search vendors for Select2 dropdowns / mobile lookups
    public function search(Request $request)
    {
        try {
            $q = $request->get('q', '');

            $vendors = Vendor::active()
                ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
                ->when($request->filled('type'), fn($query) =>
                    $query->where('vendor_type', $request->type)
                )
                ->orderBy('name')
                ->limit(30)
                ->get();

            return response()->json($vendors->map->toLookup()->values());

        } catch (\Exception $e) {
            Log::error('[Vendor] Search failed', ['message' => $e->getMessage()]);
            return response()->json([], 500);
        }
    }

    // show() — JSON, used by other modules to fetch vendor data
    public function show($id)
    {
        try {
            $vendor = Vendor::findOrFail($id);
            return response()->json($vendor);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Vendor not found.'], 404);
        }
    }
}