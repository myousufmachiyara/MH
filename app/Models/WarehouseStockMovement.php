<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseStockMovement extends Model
{
    protected $table = 'warehouse_stock_movements';

    protected $fillable = [
        'product_id', 'movement_type', 'quantity', 'amount',
        'reference_type', 'reference_id', 'doc_no', 'movement_date',
    ];

    protected $casts = [
        'quantity'      => 'decimal:3',
        'amount'        => 'decimal:2',
        'movement_date' => 'date',
    ];

    public function product() { return $this->belongsTo(Product::class, 'product_id'); }
}