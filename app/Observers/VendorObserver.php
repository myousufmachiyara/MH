<?php

namespace App\Observers;

use App\Models\Vendor;
use App\Models\ChartOfAccounts;
use App\Models\SubHeadOfAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VendorObserver
{
    /**
     * Auto-create a ChartOfAccounts entry when a vendor is created.
     *
     * Account code format: same logic as COAController
     *   prefix = hoa_id + zero-padded(shoa_id, 2)
     *   suffix = next sequence within that sub-head (3 digits)
     *
     * Sub-head used: shoa_id=5 (Accounts Payable) — vendors are payables.
     * account_type = 'vendor'
     *
     * Opening balance journal entry:
     *   If opening_balance > 0:
     *     credit type → Dr Vendor COA / Cr Accounts Payable Control (id=8)
     *     debit  type → Dr Accounts Receivable / Cr Vendor COA
     *   Stored in voucher_lines via a journal voucher so it appears in ledger.
     *
     * NOTE: This runs INSIDE the DB::transaction in VendorController::store()
     * so any failure here rolls back the entire vendor creation.
     */
    public function created(Vendor $vendor): void
    {
        try {
            // ── 1. Generate account code ─────────────────────────────
            // Sub-head 5 = Accounts Payable (hoa_id=2)
            $shoaId  = 5;
            $hoaId   = 2;
            $prefix  = $hoaId . str_pad($shoaId, 2, '0', STR_PAD_LEFT);

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
                'name'            => $vendor->name,
                'account_type'    => 'vendor',
                'receivables'     => 0,
                'payables'        => $vendor->opening_balance ?? 0,
                'credit_limit'    => 0,
                'opening_balance' => $vendor->opening_balance ?? 0,
                'opening_date'    => $vendor->opening_balance_date ?? now()->toDateString(),
                'address'         => $vendor->address,
                'contact_no'      => $vendor->phone,
                'remarks'         => 'Auto-created for vendor: ' . $vendor->name,
                'created_by'      => $vendor->created_by ?? auth()->id(),
                'updated_by'      => $vendor->updated_by ?? auth()->id(),
            ]);

            // ── 3. Link COA back to vendor ────────────────────────────
            // Update without triggering observer again
            Vendor::withoutEvents(function () use ($vendor, $coa) {
                $vendor->updateQuietly(['coa_id' => $coa->id]);
            });

            Log::info('[VendorObserver] COA created for vendor', [
                'vendor_id'    => $vendor->id,
                'vendor_name'  => $vendor->name,
                'coa_id'       => $coa->id,
                'account_code' => $accountCode,
            ]);

            // ── 4. Post opening balance journal entry ─────────────────
            // Only if opening_balance > 0
            if ($vendor->opening_balance > 0) {
                $this->postOpeningBalanceEntry($vendor, $coa);
            }

        } catch (\Exception $e) {
            Log::error('[VendorObserver] Failed to create COA for vendor', [
                'vendor_id' => $vendor->id,
                'message'   => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            // Re-throw so the transaction in VendorController rolls back
            throw $e;
        }
    }

    /**
     * Sync the COA account name if vendor name changes.
     */
    public function updated(Vendor $vendor): void
    {
        if (!$vendor->isDirty('name') || !$vendor->coa_id) {
            return;
        }

        try {
            ChartOfAccounts::where('id', $vendor->coa_id)
                ->update(['name' => $vendor->name, 'updated_by' => auth()->id()]);

            Log::info('[VendorObserver] COA name synced', [
                'vendor_id' => $vendor->id,
                'coa_id'    => $vendor->coa_id,
                'new_name'  => $vendor->name,
            ]);
        } catch (\Exception $e) {
            Log::error('[VendorObserver] Failed to sync COA name', [
                'vendor_id' => $vendor->id,
                'message'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Soft-delete the linked COA account when vendor is soft-deleted.
     */
    public function deleted(Vendor $vendor): void
    {
        if (!$vendor->coa_id) return;

        try {
            ChartOfAccounts::where('id', $vendor->coa_id)->delete();

            Log::info('[VendorObserver] COA soft-deleted with vendor', [
                'vendor_id' => $vendor->id,
                'coa_id'    => $vendor->coa_id,
            ]);
        } catch (\Exception $e) {
            Log::error('[VendorObserver] Failed to soft-delete COA', [
                'vendor_id' => $vendor->id,
                'message'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Restore the linked COA account when vendor is restored.
     */
    public function restored(Vendor $vendor): void
    {
        if (!$vendor->coa_id) return;

        try {
            ChartOfAccounts::withTrashed()->where('id', $vendor->coa_id)->restore();

            Log::info('[VendorObserver] COA restored with vendor', [
                'vendor_id' => $vendor->id,
                'coa_id'    => $vendor->coa_id,
            ]);
        } catch (\Exception $e) {
            Log::error('[VendorObserver] Failed to restore COA', [
                'vendor_id' => $vendor->id,
                'message'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function postOpeningBalanceEntry(Vendor $vendor, ChartOfAccounts $coa): void
    {
        // Control account IDs from seeder:
        //   id=8  → Accounts Payable Control  (shoa_id=5, liability)
        //   id=11 → Owner Capital / Equity    (shoa_id=7, equity)
        //
        // Vendor opening balance (credit type = company owes vendor):
        //   Dr Opening Balance Equity (11)  /  Cr Vendor COA ($coa->id)
        //
        // Vendor opening balance (debit type = vendor owes company, rare):
        //   Dr Vendor COA ($coa->id)  /  Cr Opening Balance Equity (11)

        $equityAccountId = 11; // Owner Capital — used as opening balance contra

        $drAccountId = $vendor->opening_balance_type === 'credit'
            ? $equityAccountId
            : $coa->id;

        $crAccountId = $vendor->opening_balance_type === 'credit'
            ? $coa->id
            : $equityAccountId;

        // Create journal voucher
        $voucher = \App\Models\Voucher::create([
            'voucher_no'   => 'OB-V-' . str_pad($vendor->id, 5, '0', STR_PAD_LEFT),
            'type'         => 'journal',
            'date'         => $vendor->opening_balance_date ?? now()->toDateString(),
            'party_id'     => $coa->id,
            'party_type'   => 'vendor',
            'reference'    => 'Opening Balance — ' . $vendor->name,
            'narration'    => 'Opening balance entry for vendor: ' . $vendor->name,
            'created_by'   => $vendor->created_by ?? auth()->id(),
            'updated_by'   => $vendor->updated_by ?? auth()->id(),
        ]);

        // Debit line
        \App\Models\VoucherLine::create([
            'voucher_id' => $voucher->id,
            'account_id' => $drAccountId,
            'debit'      => $vendor->opening_balance,
            'credit'     => 0,
        ]);

        // Credit line
        \App\Models\VoucherLine::create([
            'voucher_id' => $voucher->id,
            'account_id' => $crAccountId,
            'debit'      => 0,
            'credit'     => $vendor->opening_balance,
        ]);

        Log::info('[VendorObserver] Opening balance journal posted', [
            'vendor_id'  => $vendor->id,
            'voucher_id' => $voucher->id,
            'amount'     => $vendor->opening_balance,
            'type'       => $vendor->opening_balance_type,
        ]);
    }
}