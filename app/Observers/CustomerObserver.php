<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\ChartOfAccounts;
use Illuminate\Support\Facades\Log;

class CustomerObserver
{
    /**
     * Auto-create a ChartOfAccounts entry when a customer is created.
     *
     * Sub-head used: shoa_id=3 (Accounts Receivable) — customers are receivables.
     * account_type = 'customer'
     *
     * Opening balance journal entry:
     *   If opening_balance > 0:
     *     debit  type (customer owes us) → Dr Customer COA / Cr Sales Revenue (14)
     *     credit type (we owe customer) → Dr Sales Revenue / Cr Customer COA
     */
    public function created(Customer $customer): void
    {
        try {
            // ── 1. Generate account code ─────────────────────────────
            // Sub-head 3 = Accounts Receivable (hoa_id=1)
            $shoaId = 3;
            $hoaId  = 1;
            $prefix = $hoaId . str_pad($shoaId, 2, '0', STR_PAD_LEFT);

            $existingCodes = ChartOfAccounts::withTrashed()
                ->where('account_code', 'like', $prefix . '%')
                ->pluck('account_code')
                ->map(fn($code) => (int) substr($code, strlen($prefix)))
                ->sort()
                ->values();

            $nextNumber  = ($existingCodes->isEmpty() ? 0 : $existingCodes->last()) + 1;
            $accountCode = $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // ── 2. Create COA record ──────────────────────────────────
            $coa = ChartOfAccounts::create([
                'shoa_id'         => $shoaId,
                'account_code'    => $accountCode,
                'name'            => $customer->name,
                'account_type'    => 'customer',
                'receivables'     => $customer->opening_balance ?? 0,
                'payables'        => 0,
                'credit_limit'    => $customer->credit_limit ?? 0,
                'opening_balance' => $customer->opening_balance ?? 0,
                'opening_date'    => $customer->opening_balance_date ?? now()->toDateString(),
                'address'         => $customer->address,
                'contact_no'      => $customer->phone,
                'remarks'         => 'Auto-created for customer: ' . $customer->name,
                'created_by'      => $customer->created_by ?? auth()->id(),
                'updated_by'      => $customer->updated_by ?? auth()->id(),
            ]);

            // ── 3. Link COA back to customer ──────────────────────────
            Customer::withoutEvents(function () use ($customer, $coa) {
                $customer->updateQuietly(['coa_id' => $coa->id]);
            });

            Log::info('[CustomerObserver] COA created for customer', [
                'customer_id'  => $customer->id,
                'customer_name'=> $customer->name,
                'coa_id'       => $coa->id,
                'account_code' => $accountCode,
            ]);

            // ── 4. Post opening balance journal entry ─────────────────
            if ($customer->opening_balance > 0) {
                $this->postOpeningBalanceEntry($customer, $coa);
            }

        } catch (\Exception $e) {
            Log::error('[CustomerObserver] Failed to create COA for customer', [
                'customer_id' => $customer->id,
                'message'     => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function updated(Customer $customer): void
    {
        if (!$customer->isDirty('name') || !$customer->coa_id) {
            return;
        }

        try {
            ChartOfAccounts::where('id', $customer->coa_id)
                ->update(['name' => $customer->name, 'updated_by' => auth()->id()]);

            Log::info('[CustomerObserver] COA name synced', [
                'customer_id' => $customer->id,
                'coa_id'      => $customer->coa_id,
                'new_name'    => $customer->name,
            ]);
        } catch (\Exception $e) {
            Log::error('[CustomerObserver] Failed to sync COA name', [
                'customer_id' => $customer->id,
                'message'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function deleted(Customer $customer): void
    {
        if (!$customer->coa_id) return;

        try {
            ChartOfAccounts::where('id', $customer->coa_id)->delete();

            Log::info('[CustomerObserver] COA soft-deleted with customer', [
                'customer_id' => $customer->id,
                'coa_id'      => $customer->coa_id,
            ]);
        } catch (\Exception $e) {
            Log::error('[CustomerObserver] Failed to soft-delete COA', [
                'customer_id' => $customer->id,
                'message'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function restored(Customer $customer): void
    {
        if (!$customer->coa_id) return;

        try {
            ChartOfAccounts::withTrashed()->where('id', $customer->coa_id)->restore();

            Log::info('[CustomerObserver] COA restored with customer', [
                'customer_id' => $customer->id,
                'coa_id'      => $customer->coa_id,
            ]);
        } catch (\Exception $e) {
            Log::error('[CustomerObserver] Failed to restore COA', [
                'customer_id' => $customer->id,
                'message'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function postOpeningBalanceEntry(Customer $customer, ChartOfAccounts $coa): void
    {
        // Customer opening balance (debit = customer owes us — most common):
        //   Dr Customer COA  /  Cr Opening Balance Equity (11)
        //
        // Customer opening balance (credit = we owe customer, rare):
        //   Dr Opening Balance Equity (11)  /  Cr Customer COA

        $equityAccountId = 11; // Owner Capital

        $drAccountId = $customer->opening_balance_type === 'debit'
            ? $coa->id
            : $equityAccountId;

        $crAccountId = $customer->opening_balance_type === 'debit'
            ? $equityAccountId
            : $coa->id;

        $voucher = \App\Models\Voucher::create([
            'voucher_no'   => 'OB-C-' . str_pad($customer->id, 5, '0', STR_PAD_LEFT),
            'type'         => 'journal',
            'date'         => $customer->opening_balance_date ?? now()->toDateString(),
            'party_id'     => $coa->id,
            'party_type'   => 'customer',
            'reference'    => 'Opening Balance — ' . $customer->name,
            'narration'    => 'Opening balance entry for customer: ' . $customer->name,
            'created_by'   => $customer->created_by ?? auth()->id(),
            'updated_by'   => $customer->updated_by ?? auth()->id(),
        ]);

        \App\Models\VoucherLine::create([
            'voucher_id' => $voucher->id,
            'account_id' => $drAccountId,
            'debit'      => $customer->opening_balance,
            'credit'     => 0,
        ]);

        \App\Models\VoucherLine::create([
            'voucher_id' => $voucher->id,
            'account_id' => $crAccountId,
            'debit'      => 0,
            'credit'     => $customer->opening_balance,
        ]);

        Log::info('[CustomerObserver] Opening balance journal posted', [
            'customer_id' => $customer->id,
            'voucher_id'  => $voucher->id,
            'amount'      => $customer->opening_balance,
            'type'        => $customer->opening_balance_type,
        ]);
    }
}