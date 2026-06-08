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
        'receivables',
        'payables',
        'credit_limit',
        'opening_balance',   // FIX: was missing — seeder inserts this, model must allow it
        'opening_date',
        'remarks',
        'address',
        'contact_no',
        'trn',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'receivables'     => 'decimal:2',
        'payables'        => 'decimal:2',
        'credit_limit'    => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'opening_date'    => 'date',
    ];

    // ── Relationships ────────────────────────────────────────────────

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

    // ── Relationships used by new modules ────────────────────────────

    // Vendors whose individual CoA account is this record
    // (created by VendorObserver — vendor_id on vendors table → coa_id)
    public function vendor()
    {
        return $this->hasOne(Vendor::class, 'coa_id');
    }

    // Customers whose individual CoA account is this record
    public function customer()
    {
        return $this->hasOne(Customer::class, 'coa_id');
    }

    public function purchaseInvoices()
    {
        return $this->hasMany(PurchaseInvoice::class, 'vendor_id');
    }

    // ── Scopes — used by new module controllers ──────────────────────

    // Get only cash accounts (for receipt/payment voucher dropdowns)
    public function scopeCash($query)
    {
        return $query->where('account_type', 'cash');
    }

    // Get only bank accounts
    public function scopeBank($query)
    {
        return $query->where('account_type', 'bank');
    }

    // Get cash + bank together (for payment/receipt vouchers)
    public function scopeCashAndBank($query)
    {
        return $query->whereIn('account_type', ['cash', 'bank']);
    }

    // Get customer accounts (for sale invoice / receivables)
    public function scopeCustomers($query)
    {
        return $query->where('account_type', 'customer');
    }

    // Get vendor accounts (for purchase invoice / payables)
    public function scopeVendors($query)
    {
        return $query->where('account_type', 'vendor');
    }
}