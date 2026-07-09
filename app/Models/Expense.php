<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'expense_date', 'account_id', 'amount', 'payment_method',
        'narration', 'voucher_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
    ];

    public function account() { return $this->belongsTo(ChartOfAccounts::class, 'account_id'); }
    public function voucher() { return $this->belongsTo(Voucher::class, 'voucher_id'); }
}