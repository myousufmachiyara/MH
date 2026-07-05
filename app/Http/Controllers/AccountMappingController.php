<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccounts;
use App\Services\AccountMappingService;
use Illuminate\Http\Request;

class AccountMappingController extends Controller
{
    public function __construct(private AccountMappingService $service)
    {
        $this->middleware('permission:manage-coa');
    }

    public function index()
    {
        $mappings = $this->service->all();
        $accounts = ChartOfAccounts::with('subHead')
            ->orderBy('account_code')
            ->get();

        // your view lives at resources/views/accounts/mapping.blade.php
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