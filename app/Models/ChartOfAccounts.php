<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChartOfAccounts extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'shoa_id',
        'name',
        'account_code',
        'account_type',
        'opening_balance',
        'opening_date',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'opening_date'    => 'date',
    ];

    public function subHeadOfAccount()
    {
        return $this->belongsTo(SubHeadOfAccounts::class, 'shoa_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Business-account scopes only (no customer/vendor)
    public function scopeCash($q)        { return $q->where('account_type', 'cash'); }
    public function scopeBank($q)        { return $q->where('account_type', 'bank'); }
    public function scopeCashAndBank($q) { return $q->whereIn('account_type', ['cash', 'bank']); }
}