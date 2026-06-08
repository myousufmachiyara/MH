<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coa_id',
        'name',
        'vendor_type',
        'phone',
        'email',
        'contact_person',
        'address',
        'city',
        'ntn',
        'opening_balance',
        'opening_balance_type',
        'opening_balance_date',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'is_active'       => 'boolean',
        'opening_balance_date' => 'date',
    ];

    // ── Vendor type labels ───────────────────────────────────────────

    public const TYPES = [
        'spinning_mill'    => 'Spinning Mill',
        'weaving_mill'     => 'Weaving Mill',
        'processing_mill'  => 'Processing Mill',
        'packager'         => 'Packager',
        'courier'          => 'Courier',
        'other'            => 'Other',
    ];

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->vendor_type] ?? ucfirst($this->vendor_type);
    }

    // ── Relationships ────────────────────────────────────────────────

    public function coaAccount()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'coa_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Services this vendor can perform (pivot — built in Services module)
    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_vendor')
                    ->using(ServiceVendor::class)
                    ->withPivot('id', 'rate', 'currency', 'notes')
                    ->withTimestamps();
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('vendor_type', $type);
    }

    // ── AJAX search helper ───────────────────────────────────────────
    // Used by helpers.vendors.search route for Select2 dropdowns

    public function toLookup(): array
    {
        return [
            'id'       => $this->id,
            'text'     => $this->name,
            'type'     => $this->getTypeLabel(),
            'phone'    => $this->phone,
            'coa_id'   => $this->coa_id,
        ];
    }
}