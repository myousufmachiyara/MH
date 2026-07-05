<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountMapping extends Model
{
    protected $table = 'account_mappings';

    protected $fillable = ['role_key', 'account_id'];

    // System roles the voucher engine posts to.
    // key => [label, hint, suggested account_type for the picker]
    public const ROLES = [
        'accounts_receivable' => ['Accounts Receivable', 'Customers who owe you (sales post here).', 'receivable'],
        'accounts_payable'    => ['Accounts Payable', 'Vendors you owe (purchases post here).', 'payable'],
        'sales_revenue'       => ['Sales Revenue', 'Income earned from sales.', 'revenue'],
        'sales_tax_payable'   => ['Sales Tax Payable', 'Tax collected on sales, owed to govt.', 'tax'],
        'purchase_tax'        => ['Purchase Tax', 'Input tax paid on purchases.', 'tax'],
        'stock_in_hand'       => ['Stock in Hand', 'Inventory asset value.', 'inventory'],
        'cogs'                => ['Cost of Goods Sold', 'Cost of inventory sold.', 'cogs'],
        'cash'                => ['Cash', 'Cash payments and receipts.', 'cash'],
        'bank'                => ['Bank', 'Bank payments and receipts.', 'bank'],
        'processing_charges'  => ['Processing / Labour Charges', 'Vendor processing fee on job receive.', 'service_cost'],
        'opening_balance_equity' => ['Opening Balance Equity', 'Offset for opening balances.', 'equity'],
    ];

    public function account()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'account_id');
    }
}