<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\VoucherEntry;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public function post(
        string $type,
        string $voucherDate,
        array $lines,
        ?string $narration = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null,
        ?array $attachments = null
    ): Voucher {
        if (count($lines) < 2) {
            throw new \Exception('A voucher needs at least two lines.');
        }

        $totalDebit  = 0;
        $totalCredit = 0;
        foreach ($lines as $line) {
            $totalDebit  += (float) ($line['debit']  ?? 0);
            $totalCredit += (float) ($line['credit'] ?? 0);
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new \Exception(
                "Voucher does not balance: Debit {$totalDebit} != Credit {$totalCredit}."
            );
        }

        if ($totalDebit == 0) {
            throw new \Exception('Voucher amount cannot be zero.');
        }

        return DB::transaction(function () use (
            $type, $voucherDate, $lines, $narration, $referenceType, $referenceId, $userId, $attachments
        ) {
            $voucher = Voucher::create([
                'voucher_no'     => $this->generateVoucherNo($type),
                'type'           => $type,
                'voucher_date'   => $voucherDate,
                'narration'      => $narration,
                'attachments'    => $attachments,
                'reference_type' => $referenceType,
                'reference_id'   => $referenceId,
                'created_by'     => $userId,
                'updated_by'     => $userId,
            ]);

            foreach ($lines as $line) {
                VoucherEntry::create([
                    'voucher_id' => $voucher->id,
                    'account_id' => $line['account_id'],
                    'party_type' => $line['party_type'] ?? null,
                    'party_id'   => $line['party_id']   ?? null,
                    'debit'      => $line['debit']  ?? 0,
                    'credit'     => $line['credit'] ?? 0,
                    'narration'  => $line['narration'] ?? $narration,
                ]);
            }

            return $voucher;
        });
    }

    public function deleteByReference(string $referenceType, int $referenceId): void
    {
        DB::transaction(function () use ($referenceType, $referenceId) {
            $vouchers = Voucher::where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->get();

            foreach ($vouchers as $voucher) {
                VoucherEntry::where('voucher_id', $voucher->id)->delete();
                $voucher->delete();
            }
        });
    }

    private function generateVoucherNo(string $type): string
    {
        $prefix = match ($type) {
            'payment' => 'PV',
            'receipt' => 'RV',
            'journal' => 'JV',
            'contra'  => 'CV',
            default   => 'SV',
        };

        $last = Voucher::where('type', $type)
            ->where('voucher_no', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('voucher_no');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return $prefix . '-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }
}