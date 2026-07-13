<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    protected $table = 'purchase_return_items';

    protected $fillable = ['purchase_return_id', 'purchase_item_id', 'product_id', 'quantity', 'unit_price', 'amount'];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_price' => 'decimal:2',
        'amount'     => 'decimal:2',
    ];

    public function purchaseReturn() { return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id'); }
    public function purchaseItem()   { return $this->belongsTo(PurchaseItem::class, 'purchase_item_id'); }
    public function product()        { return $this->belongsTo(Product::class, 'product_id'); }
}