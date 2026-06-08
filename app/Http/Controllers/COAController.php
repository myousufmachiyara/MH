<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccounts;
use App\Models\SubHeadOfAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class COAController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // Canonical list of valid account types — single source of truth.
    // Must stay in sync with:
    //   • coa.blade.php $accountTypes array (display labels)
    //   • DatabaseSeeder $coaData account_type values
    //   • New module observers (VendorObserver, CustomerObserver)
    // ─────────────────────────────────────────────────────────────────
    public const ACCOUNT_TYPES = [
        // Party accounts — created dynamically by observers
        'customer',
        'vendor',

        // Asset accounts
        'cash',
        'bank',
        'inventory',
        'receivable',   // loans given out / advance paid

        // Liability accounts
        'liability',
        'payable',      // loans taken / advance received

        // Equity
        'equity',

        // Revenue
        'revenue',

        // Expense accounts
        'cogs',           // Cost of Goods Sold
        'expenses',       // General expenses
        'service_cost',   // Outsourced service vendor charges (weaving, processing etc.)
        'freight',        // Freight & logistics costs
        'sampling',       // Sample production & courier costs
        'packaging',      // Packaging material costs
    ];

    // ─────────────────────────────────────────────────────────────────
    // Seeded account IDs that must never be deleted.
    // Range covers all 32 accounts seeded in DatabaseSeeder.
    // ─────────────────────────────────────────────────────────────────
    private const PROTECTED_ACCOUNT_ID_CEILING = 32;

    // ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $subHeadOfAccounts = SubHeadOfAccounts::with('headOfAccount')
            ->orderBy('id')
            ->get();

        $query = ChartOfAccounts::with('subHeadOfAccount');

        if ($request->filled('subhead') && $request->subhead !== 'all') {
            $query->where('shoa_id', $request->subhead);
        }

        $chartOfAccounts = $query->latest()->get();

        return view('accounts.coa', compact('chartOfAccounts', 'subHeadOfAccounts'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        try {
            Log::info('[COA] Store called', ['user_id' => auth()->id()]);

            $validated = $request->validate([
                'shoa_id'      => 'required|exists:sub_head_of_accounts,id',
                'name'         => [
                    'required', 'string', 'max:255',
                    Rule::unique('chart_of_accounts')->whereNull('deleted_at'),
                ],
                'account_type'    => ['nullable', 'string', Rule::in(self::ACCOUNT_TYPES)],
                'receivables'     => 'required|numeric',
                'payables'        => 'required|numeric',
                'credit_limit'    => 'required|numeric',
                'opening_balance' => 'nullable|numeric',
                'opening_date'    => 'required|date',
                'remarks'         => 'nullable|string|max:800',
                'address'         => 'nullable|string|max:250',
                'contact_no'      => 'nullable|string|max:250',
            ]);

            DB::beginTransaction();

            // ── Auto-generate account code ────────────────────────────
            $subHead = SubHeadOfAccounts::findOrFail($request->shoa_id);
            $prefix  = $subHead->hoa_id . str_pad($subHead->id, 2, '0', STR_PAD_LEFT);

            $existingCodes = ChartOfAccounts::withTrashed()
                ->where('account_code', 'like', $prefix . '%')
                ->pluck('account_code')
                ->map(fn($code) => (int) substr($code, strlen($prefix)))
                ->sort()
                ->values();

            $nextNumber  = ($existingCodes->isEmpty() ? 0 : $existingCodes->last()) + 1;
            $accountCode = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            Log::info('[COA] Generated account code', ['code' => $accountCode]);

            $account = ChartOfAccounts::create([
                'shoa_id'         => $request->shoa_id,
                'account_code'    => $accountCode,
                'name'            => $request->name,
                'account_type'    => $request->account_type,
                'receivables'     => $request->receivables,
                'payables'        => $request->payables,
                'credit_limit'    => $request->credit_limit,
                'opening_balance' => $request->opening_balance ?? 0,
                'opening_date'    => $request->opening_date,
                'remarks'         => $request->remarks,
                'address'         => $request->address,
                'contact_no'      => $request->contact_no,
                'created_by'      => auth()->id(),
                'updated_by'      => auth()->id(),
            ]);

            DB::commit();

            Log::info('[COA] Account created', ['id' => $account->id, 'code' => $accountCode]);

            return redirect()->route('coa.index')
                ->with('success', 'Account "' . $account->name . '" created successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withErrors($e->errors());

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[COA] Store error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return redirect()->back()
                ->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Returns JSON for the edit modal AJAX call
    public function edit($id)
    {
        try {
            $account = ChartOfAccounts::with('subHeadOfAccount')->findOrFail($id);
            return response()->json($account);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Account not found'], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'shoa_id'         => 'required|exists:sub_head_of_accounts,id',
                'name'            => [
                    'required', 'string', 'max:255',
                    Rule::unique('chart_of_accounts')->ignore($id)->whereNull('deleted_at'),
                ],
                'account_type'    => ['nullable', 'string', Rule::in(self::ACCOUNT_TYPES)],
                'receivables'     => 'required|numeric',
                'payables'        => 'required|numeric',
                'credit_limit'    => 'required|numeric',
                'opening_balance' => 'nullable|numeric',
                'opening_date'    => 'required|date',
                'remarks'         => 'nullable|string|max:800',
                'address'         => 'nullable|string|max:250',
                'contact_no'      => 'nullable|string|max:250',
            ]);

            DB::beginTransaction();

            $account = ChartOfAccounts::findOrFail($id);

            $account->update([
                'shoa_id'         => $request->shoa_id,
                'name'            => $request->name,
                'account_type'    => $request->account_type,
                'receivables'     => $request->receivables,
                'payables'        => $request->payables,
                'credit_limit'    => $request->credit_limit,
                'opening_balance' => $request->opening_balance ?? $account->opening_balance,
                'opening_date'    => $request->opening_date,
                'remarks'         => $request->remarks,
                'address'         => $request->address,
                'contact_no'      => $request->contact_no,
                'updated_by'      => auth()->id(),
            ]);

            DB::commit();

            Log::info('[COA] Account updated', ['id' => $id, 'user' => auth()->id()]);

            return redirect()->route('coa.index')
                ->with('success', 'Account updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withErrors($e->errors());

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[COA] Update error', ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // show() — used by other modules to fetch account data as JSON
    public function show($id)
    {
        try {
            $account = ChartOfAccounts::with('subHeadOfAccount')->findOrFail($id);
            return response()->json($account);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Account not found'], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // search() — AJAX endpoint for account search dropdowns in vouchers,
    // purchase/sale invoices etc. Used by helpers.accounts.search route.
    public function search(Request $request)
    {
        $q = $request->get('q', '');

        $accounts = ChartOfAccounts::where('name', 'like', "%{$q}%")
            ->orWhere('account_code', 'like', "%{$q}%")
            ->when($request->filled('type'), fn($query) =>
                $query->whereIn('account_type', explode(',', $request->type))
            )
            ->select('id', 'name', 'account_code', 'account_type')
            ->limit(30)
            ->get();

        return response()->json($accounts);
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        try {
            $account = ChartOfAccounts::findOrFail($id);

            // Guard: seeded system accounts (IDs 1–32) cannot be deleted
            if ($account->id <= self::PROTECTED_ACCOUNT_ID_CEILING) {
                return redirect()->back()
                    ->with('error', 'System account "' . $account->name . '" cannot be deleted.');
            }

            // Guard: accounts linked to a vendor or customer cannot be deleted
            // (the observer creates them; the module destroy handles cleanup)
            if ($account->vendor || $account->customer) {
                return redirect()->back()
                    ->with('error', 'This account is linked to a vendor or customer. Delete the party record instead.');
            }

            $account->delete();

            Log::info('[COA] Account deleted', ['id' => $id, 'user' => auth()->id()]);

            return redirect()->route('coa.index')
                ->with('success', 'Account deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[COA] Destroy error', ['message' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Could not delete account. It may be in use.');
        }
    }
}