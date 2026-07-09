<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    protected $table = 'sales';

    protected $fillable = [
        'sale_no', 'customer_id', 'order_id', 'sale_date',
        'subtotal', 'tax_amount', 'total_amount', 'status', 'remarks',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'sale_date'    => 'date',
        'subtotal'     => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(Customer::class, 'customer_id'); }
    public function order()    { return $this->belongsTo(Order::class, 'order_id'); }
    public function items()    { return $this->hasMany(SaleItem::class, 'sale_id'); }
}