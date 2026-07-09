<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'product_id', 'movement_type', 'quantity', 'amount',
        'reference_type', 'reference_id', 'location', 'movement_date',
    ];

    protected $casts = [
        'quantity'      => 'decimal:3',
        'amount'        => 'decimal:2',
        'movement_date' => 'date',
    ];

    public function product() { return $this->belongsTo(Product::class, 'product_id'); }

    public function scopeForProduct($q, int $productId) { return $q->where('product_id', $productId); }
}