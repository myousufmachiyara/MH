<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $table = 'vendors';

    public const TYPES = [
        'spinning_mill'   => 'Spinning Mill',
        'weaving_mill'    => 'Weaving Mill',
        'processing_mill' => 'Processing Mill',
        'packager'        => 'Packager',
        'courier'         => 'Courier',
        'other'           => 'Other',
    ];

    protected $fillable = [
        'name', 'vendor_type', 'phone', 'email', 'contact_person',
        'address', 'city', 'ntn', 'opening_balance', 'opening_type',
        'opening_balance_date', 'notes', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'opening_balance'      => 'decimal:2',
        'opening_balance_date' => 'date',
        'is_active'             => 'boolean',
    ];

    public function getOpeningBalanceSignedAttribute(): float
    {
        $amt = (float) $this->opening_balance;
        return $this->opening_type === 'payable' ? -abs($amt) : abs($amt);
    }

    public function voucherEntries()
    {
        return $this->hasMany(VoucherEntry::class, 'party_id')->where('party_type', 'vendor');
    }

    public function getBalanceAttribute(): float
    {
        $net = VoucherEntry::where('party_type', 'vendor')
            ->where('party_id', $this->id)
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
            ->value('net');

        return $this->opening_balance_signed + (float) $net;
    }

    public function scopeActive($q) { return $q->where('is_active', true); }

    // Compact shape for Select2 / mobile dropdowns
    public function toLookup(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'vendor_type' => $this->vendor_type,
            'balance'     => $this->balance,
        ];
    }
}