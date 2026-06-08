<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'unit_id',
        'expense_account_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function unit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'unit_id');
    }

    public function expenseAccount()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'expense_account_id');
    }

    /**
     * Vendors that can perform this service.
     * Pivot columns: rate, currency, notes
     */
    public function vendors()
    {
        return $this->belongsToMany(Vendor::class, 'service_vendor')
                    ->using(ServiceVendor::class)
                    ->withPivot('id', 'rate', 'currency', 'notes')
                    ->withTimestamps();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── AJAX lookup helper ───────────────────────────────────────────

    public function toLookup(): array
    {
        return [
            'id'   => $this->id,
            'text' => $this->name,
            'unit' => optional($this->unit)->shortcode,
        ];
    }
}