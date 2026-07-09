<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $table = 'sale_items';

    protected $fillable = ['sale_id', 'product_id', 'quantity', 'unit_price', 'amount'];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_price' => 'decimal:2',
        'amount'     => 'decimal:2',
    ];

    public function sale()    { return $this->belongsTo(Sale::class, 'sale_id'); }
    public function product() { return $this->belongsTo(Product::class, 'product_id'); }
}