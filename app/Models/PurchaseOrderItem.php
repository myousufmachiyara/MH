<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';

    protected $fillable = ['purchase_order_id', 'product_id', 'quantity', 'estimated_price', 'amount'];

    protected $casts = [
        'quantity'        => 'decimal:3',
        'estimated_price' => 'decimal:2',
        'amount'          => 'decimal:2',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function product()       { return $this->belongsTo(Product::class, 'product_id'); }
}