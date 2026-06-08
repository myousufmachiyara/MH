<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coa_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'city',
        'ntn',
        'opening_balance',
        'opening_balance_type',
        'opening_balance_date',
        'credit_limit',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_limit'    => 'decimal:2',
        'is_active'       => 'boolean',
        'opening_balance_date' => 'date',
    ];

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

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── AJAX search helper ───────────────────────────────────────────

    public function toLookup(): array
    {
        return [
            'id'             => $this->id,
            'text'           => $this->name,
            'contact_person' => $this->contact_person,
            'phone'          => $this->phone,
            'coa_id'         => $this->coa_id,
            'credit_limit'   => $this->credit_limit,
        ];
    }
}