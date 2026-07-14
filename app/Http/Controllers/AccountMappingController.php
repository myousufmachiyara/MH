<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccounts;
use App\Services\AccountMappingService;
use Illuminate\Http\Request;

class AccountMappingController extends Controller
{
    public function __construct(private AccountMappingService $service)
    {
        // Viewing the mapping screen requires coa.index
        $this->middleware('check.permission:coa.index')->only('index');
        // Saving mappings requires coa.edit (more sensitive — drives the voucher engine)
        $this->middleware('check.permission:coa.edit')->only('update');
    }

    public function index()
    {
        $mappings = $this->service->all();
        $accounts = ChartOfAccounts::with('subHeadOfAccount')
            ->orderBy('account_code')
            ->get();

        return view('accounts.mapping', compact('mappings', 'accounts'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mappings'   => ['required', 'array'],
            'mappings.*' => ['nullable', 'exists:chart_of_accounts,id'],
        ]);

        $this->service->saveAll($data['mappings']);

        return back()->with('success', 'Account mappings saved.');
    }
}