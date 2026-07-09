<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherEntry extends Model
{
    protected $table = 'voucher_entries';

    protected $fillable = [
        'voucher_id', 'account_id', 'party_type', 'party_id',
        'debit', 'credit', 'narration',
    ];

    protected $casts = [
        'debit'  => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function voucher() { return $this->belongsTo(Voucher::class, 'voucher_id'); }
    public function account() { return $this->belongsTo(ChartOfAccounts::class, 'account_id'); }

    // Resolve the party model dynamically (customer or vendor)
    public function party()
    {
        return $this->party_type === 'vendor'
            ? $this->belongsTo(Vendor::class, 'party_id')
            : $this->belongsTo(Customer::class, 'party_id');
    }

    public function scopeForAccount($q, int $accountId) { return $q->where('account_id', $accountId); }
    public function scopeForParty($q, string $type, int $id)
    {
        return $q->where('party_type', $type)->where('party_id', $id);
    }
}