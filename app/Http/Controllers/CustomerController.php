<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $customers = Customer::orderBy('name')->get();
        return view('accounts.customers', compact('customers'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'contact_person'        => 'nullable|string|max:255',
            'phone'                 => 'nullable|string|max:50',
            'email'                 => 'nullable|email|max:255',
            'address'               => 'nullable|string|max:500',
            'city'                  => 'nullable|string|max:100',
            'ntn'                   => 'nullable|string|max:50',
            'opening_balance'       => 'nullable|numeric|min:0',
            'opening_balance_type'  => 'nullable|in:debit,credit',
            'opening_balance_date'  => 'nullable|date',
            'credit_limit'          => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string|max:1000',
            'is_active'             => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $customer = Customer::create([
                'name'                  => $request->name,
                'contact_person'        => $request->contact_person,
                'phone'                 => $request->phone,
                'email'                 => $request->email,
                'address'               => $request->address,
                'city'                  => $request->city,
                'ntn'                   => $request->ntn,
                'opening_balance'       => $request->opening_balance ?? 0,
                'opening_balance_type'  => $request->opening_balance_type ?? 'debit',
                'opening_balance_date'  => $request->opening_balance_date ?? now()->toDateString(),
                'credit_limit'          => $request->credit_limit ?? 0,
                'notes'                 => $request->notes,
                'is_active'             => $request->boolean('is_active', true),
                'created_by'            => auth()->id(),
                'updated_by'            => auth()->id(),
            ]);
            // CustomerObserver::created() fires here — creates COA, posts opening balance

            DB::commit();

            Log::info('[Customer] Created', ['id' => $customer->id, 'by' => auth()->id()]);

            return redirect()->route('customers.index')
                ->with('success', 'Customer "' . $customer->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Customer] Store failed', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function edit($id)
    {
        try {
            $customer = Customer::findOrFail($id);
            return response()->json($customer);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Customer not found.'], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'                  => 'required|string|max:255',
            'contact_person'        => 'nullable|string|max:255',
            'phone'                 => 'nullable|string|max:50',
            'email'                 => 'nullable|email|max:255',
            'address'               => 'nullable|string|max:500',
            'city'                  => 'nullable|string|max:100',
            'ntn'                   => 'nullable|string|max:50',
            'opening_balance'       => 'nullable|numeric|min:0',
            'opening_balance_type'  => 'nullable|in:debit,credit',
            'opening_balance_date'  => 'nullable|date',
            'credit_limit'          => 'nullable|numeric|min:0',
            'notes'                 => 'nullable|string|max:1000',
            'is_active'             => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $customer = Customer::findOrFail($id);

            $customer->update([
                'name'                  => $request->name,
                'contact_person'        => $request->contact_person,
                'phone'                 => $request->phone,
                'email'                 => $request->email,
                'address'               => $request->address,
                'city'                  => $request->city,
                'ntn'                   => $request->ntn,
                'opening_balance'       => $request->opening_balance ?? $customer->opening_balance,
                'opening_balance_type'  => $request->opening_balance_type ?? $customer->opening_balance_type,
                'opening_balance_date'  => $request->opening_balance_date ?? $customer->opening_balance_date,
                'credit_limit'          => $request->credit_limit ?? $customer->credit_limit,
                'notes'                 => $request->notes,
                'is_active'             => $request->boolean('is_active', $customer->is_active),
                'updated_by'            => auth()->id(),
            ]);
            // CustomerObserver::updated() fires — syncs COA name if changed

            DB::commit();

            Log::info('[Customer] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('customers.index')
                ->with('success', 'Customer updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Customer] Update failed', [
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
            $customer = Customer::findOrFail($id);

            // Guard: cannot delete if customer has sale invoices or projects
            $hasInvoices = DB::table('sale_invoices')
                ->where('customer_id', $id)->exists();

            $hasProjects = DB::table('projects')
                ->where('customer_id', $id)->exists();

            if ($hasInvoices || $hasProjects) {
                DB::rollBack();
                return redirect()->back()
                    ->with('error', 'Cannot delete "' . $customer->name . '" — it has linked invoices or projects. Deactivate instead.');
            }

            $customer->delete(); // CustomerObserver::deleted() fires — soft-deletes COA

            DB::commit();

            Log::info('[Customer] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('customers.index')
                ->with('success', 'Customer deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Customer] Destroy failed', [
                'id'      => $id,
                'message' => $e->getMessage(),
            ]);
            return redirect()->back()
                ->with('error', 'Could not delete customer. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX: search customers for Select2 dropdowns
    // Route: helpers.customers.search
    public function search(Request $request)
    {
        try {
            $q = $request->get('q', '');

            $customers = Customer::active()
                ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
                ->orderBy('name')
                ->limit(30)
                ->get();

            return response()->json($customers->map->toLookup()->values());

        } catch (\Exception $e) {
            Log::error('[Customer] Search failed', ['message' => $e->getMessage()]);
            return response()->json([], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX: get open purchase orders for a customer (used in sale invoice)
    // Route: helpers.customer.purchase_orders
    public function getPurchaseOrders($customerId)
    {
        try {
            $orders = DB::table('purchase_orders')
                ->where('customer_id', $customerId)
                ->whereIn('status', ['confirmed', 'partial'])
                ->select('id', 'po_number', 'date', 'status')
                ->orderByDesc('date')
                ->get();

            return response()->json(['success' => true, 'orders' => $orders]);

        } catch (\Exception $e) {
            Log::error('[Customer] getPurchaseOrders failed', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'orders' => []], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function show($id)
    {
        try {
            $customer = Customer::with('coaAccount')->findOrFail($id);
            return response()->json($customer);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Customer not found.'], 404);
        }
    }
}