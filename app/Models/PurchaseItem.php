<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $table = 'purchase_items';

    protected $fillable = ['purchase_id', 'product_id', 'quantity', 'unit_price', 'amount'];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_price' => 'decimal:2',
        'amount'     => 'decimal:2',
    ];

    public function purchase() { return $this->belongsTo(Purchase::class, 'purchase_id'); }
    public function product()  { return $this->belongsTo(Product::class, 'product_id'); }
}