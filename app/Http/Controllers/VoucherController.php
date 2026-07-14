<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\ChartOfAccounts;
use App\Models\Customer;
use App\Models\Vendor;
use App\Services\VoucherService;
use App\Services\AccountMappingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VoucherController extends Controller
{
    public function __construct(
        private VoucherService $voucherService,
        private AccountMappingService $mappingService
    ) {}

    private function validTypes(): array
    {
        return ['payment', 'receipt', 'journal', 'contra'];
    }

    public function index(string $type)
    {
        abort_unless(in_array($type, $this->allTypes()), 404);

        $query = Voucher::with('entries.account')
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        // 'purchase'/'sale' tabs show system-generated vouchers referencing those modules
        if (in_array($type, ['purchase', 'sale'])) {
            $query->where('reference_type', ucfirst($type));
        } else {
            $query->where('type', $type);
        }

        $vouchers = $query->get()->map(function ($v) {
            $v->is_auto = !is_null($v->reference_type);

            $v->display_debits = $v->entries->where('debit', '>', 0)->map(fn($e) => [
                'account' => $e->account->name ?? '—',
                'amount'  => $e->debit,
            ])->values();

            $v->display_credits = $v->entries->where('credit', '>', 0)->map(fn($e) => [
                'account' => $e->account->name ?? '—',
                'amount'  => $e->credit,
            ])->values();

            $v->display_total = $v->entries->sum('debit'); // debit total == credit total, always

            $v->reference_label = $v->reference_type
                ? $v->reference_type . ' #' . $v->reference_id
                : null;

            $v->reference_link = $this->referenceLink($v->reference_type, $v->reference_id);

            return $v;
        });

        $accounts = ChartOfAccounts::orderBy('account_code')->get();

        return view('vouchers.index', compact('vouchers', 'type', 'accounts'));
    }

    private function allTypes(): array
    {
        return ['payment', 'receipt', 'journal', 'contra', 'purchase', 'sale'];
    }

    private function referenceLink(?string $referenceType, ?int $referenceId): ?string
    {
        if (!$referenceType || !$referenceId) return null;

        return match ($referenceType) {
            'Purchase'       => route('purchase_invoices.edit', $referenceId),
            'PurchaseReturn' => route('purchase_returns.edit', $referenceId),
            'JobOrderReceive'=> route('job_receives.show', $referenceId),
            'Sale'           => null, // route added once Sale module exists
            default          => null,
        };
    }

    public function create(string $type)
    {
        abort_unless(in_array($type, $this->validTypes()), 404);

        $accounts  = ChartOfAccounts::orderBy('account_code')->get();
        $customers = Customer::active()->orderBy('name')->get();
        $vendors   = Vendor::active()->orderBy('name')->get();

        return view('vouchers.form', compact('type', 'accounts', 'customers', 'vendors'));
    }

    public function store(Request $request, string $type)
    {
        abort_unless(in_array($type, ['payment', 'receipt', 'journal', 'contra']), 404);

        $attachments = $this->handleUploads($request);

        if (in_array($type, ['payment', 'receipt'])) {
            return $this->storeSimple($request, $type, $attachments);
        }

        return $this->storeJournalOrContra($request, $type, $attachments);
    }

    private function storeSimple(Request $request, string $type, ?array $attachments)
    {
        $request->validate([
            'voucher_date' => 'required|date',
            'amount'       => 'required|numeric|min:0.01',
            'ac_dr_sid'    => 'required|exists:chart_of_accounts,id',
            'ac_cr_sid'    => 'required|exists:chart_of_accounts,id|different:ac_dr_sid',
            'party_type'   => 'nullable|in:customer,vendor',
            'party_id'     => 'nullable|integer',
            'remarks'      => 'nullable|string|max:500',
        ]);

        try {
            $amount = (float) $request->amount;

            // Note: the "Debit Account" and "Credit Account" fields on the form
            // map directly to a single line each — this is a simple two-line
            // voucher (Payment/Receipt), same shape as before, just posted
            // through voucher_entries now instead of ac_dr_sid/ac_cr_sid columns.
            $lines = [
                [
                    'account_id' => $request->ac_dr_sid,
                    'debit'      => $amount,
                    'credit'     => 0,
                    'party_type' => $request->party_type,
                    'party_id'   => $request->party_id,
                ],
                [
                    'account_id' => $request->ac_cr_sid,
                    'debit'      => 0,
                    'credit'     => $amount,
                ],
            ];

            $this->voucherService->post(
                $type,
                $request->voucher_date,
                $lines,
                $request->remarks,
                null,
                null,
                auth()->id(),
                $attachments
            );

            return redirect()->route('vouchers.index', $type)
                ->with('success', ucfirst($type) . ' voucher posted successfully.');

        } catch (\Exception $e) {
            Log::error("[Voucher:{$type}] Store failed", ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function handleUploads(Request $request): ?array
    {
        if (!$request->hasFile('attachments')) {
            return null;
        }

        $paths = [];
        foreach ($request->file('attachments') as $file) {
            $paths[] = $file->store('voucher_attachments', 'public');
        }

        return $paths;
    }

    private function storePaymentOrReceipt(Request $request, string $type, ?array $attachments)
    {
        $request->validate([
            'voucher_date' => 'required|date',
            'amount'       => 'required|numeric|min:0.01',
            'method'       => 'required|in:cash,bank',
            'party_type'   => 'nullable|in:customer,vendor',
            'party_id'     => 'nullable|integer',
            'account_id'   => 'required|exists:chart_of_accounts,id',
            'narration'    => 'nullable|string|max:500',
            'attachments'  => 'nullable|array',
            'attachments.*'=> 'file|max:5120', // 5MB per file
        ]);

        try {
            $cashBankAccountId = $this->mappingService->accountId(
                $request->method === 'cash' ? 'cash' : 'bank'
            );

            $amount = (float) $request->amount;

            if ($type === 'payment') {
                $lines = [
                    [
                        'account_id' => $request->account_id,
                        'debit'      => $amount,
                        'credit'     => 0,
                        'party_type' => $request->party_type,
                        'party_id'   => $request->party_id,
                    ],
                    [
                        'account_id' => $cashBankAccountId,
                        'debit'      => 0,
                        'credit'     => $amount,
                    ],
                ];
            } else {
                $lines = [
                    [
                        'account_id' => $cashBankAccountId,
                        'debit'      => $amount,
                        'credit'     => 0,
                    ],
                    [
                        'account_id' => $request->account_id,
                        'debit'      => 0,
                        'credit'     => $amount,
                        'party_type' => $request->party_type,
                        'party_id'   => $request->party_id,
                    ],
                ];
            }

            $this->voucherService->post(
                $type,
                $request->voucher_date,
                $lines,
                $request->narration,
                null,
                null,
                auth()->id(),
                $attachments
            );

            return redirect()->route('vouchers.index', $type)
                ->with('success', ucfirst($type) . ' voucher posted successfully.');

        } catch (\Exception $e) {
            Log::error("[Voucher:{$type}] Store failed", ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function storeJournalOrContra(Request $request, string $type, ?array $attachments)
    {
        $request->validate([
            'voucher_date'         => 'required|date',
            'narration'            => 'nullable|string|max:500',
            'lines'                => 'required|array|min:2',
            'lines.*.account_id'   => 'required|exists:chart_of_accounts,id',
            'lines.*.debit'        => 'nullable|numeric|min:0',
            'lines.*.credit'       => 'nullable|numeric|min:0',
            'lines.*.party_type'   => 'nullable|in:customer,vendor',
            'lines.*.party_id'     => 'nullable|integer',
            'attachments'          => 'nullable|array',
            'attachments.*'        => 'file|max:5120',
        ]);

        try {
            $lines = collect($request->lines)->map(fn($l) => [
                'account_id' => $l['account_id'],
                'debit'      => $l['debit']  ?? 0,
                'credit'     => $l['credit'] ?? 0,
                'party_type' => $l['party_type'] ?? null,
                'party_id'   => $l['party_id']   ?? null,
            ])->toArray();

            $this->voucherService->post(
                $type,
                $request->voucher_date,
                $lines,
                $request->narration,
                null,
                null,
                auth()->id(),
                $attachments
            );

            return redirect()->route('vouchers.index', $type)
                ->with('success', ucfirst($type) . ' voucher posted successfully.');

        } catch (\Exception $e) {
            Log::error("[Voucher:{$type}] Store failed", ['message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(string $type, $id)
    {
        $voucher = Voucher::with('entries.account', 'entries.party')->findOrFail($id);

        return response()->json([
            'voucher_no' => $voucher->voucher_no,
            'date'       => $voucher->voucher_date->format('Y-m-d'),
            'amount'     => $voucher->entries->sum('debit'),
            'remarks'    => $voucher->narration,
            'is_simple'  => false, // always show read-only entries now
            'entries'    => $voucher->entries->map(fn($e) => [
                'account_name' => $e->account->name ?? '—',
                'party_name'   => $e->party->name ?? null,
                'debit'        => (float) $e->debit,
                'credit'       => (float) $e->credit,
                'narration'    => $e->narration,
            ]),
        ]);
    }

    public function destroy(string $type, $id)
    {
        try {
            $voucher = Voucher::findOrFail($id);

            if ($voucher->reference_type) {
                return back()->with('error',
                    'This voucher was auto-generated by ' . $voucher->reference_type .
                    '. Delete/cancel that record instead.');
            }

            if ($voucher->attachments) {
                foreach ($voucher->attachments as $path) {
                    Storage::disk('public')->delete($path);
                }
            }

            $voucher->entries()->delete();
            $voucher->delete();

            Log::info('[Voucher] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('vouchers.index', $type)
                ->with('success', 'Voucher deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[Voucher] Destroy failed', ['id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', 'Could not delete voucher.');
        }
    }
}