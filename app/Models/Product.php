<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'subcategory_id',
        'name',
        'sku',
        'description',
        'opening_stock',
        'selling_price',
        'measurement_unit',
        'is_active',
        'track_lots',
    ];

    protected $casts = [
        'opening_stock' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'is_active'     => 'boolean',
        'track_lots'    => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(ProductSubcategory::class, 'subcategory_id');
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function measurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit');
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'product_id');
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'product_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Weighted Average Cost — computed from actual purchases, not stored.
    // WAC = total value received / total quantity received
    public function getWeightedAverageCostAttribute(): float
    {
        $totals = $this->stockMovements()
            ->where('movement_type', 'Purchase')
            ->selectRaw('COALESCE(SUM(amount),0) as total_value, COALESCE(SUM(quantity),0) as total_qty')
            ->first();

        $qty = (float) ($totals->total_qty ?? 0);
        if ($qty <= 0) {
            return 0;
        }

        return (float) $totals->total_value / $qty;
    }

    // Current stock on hand — opening + all movements (in - out)
    public function getCurrentStockAttribute(): float
    {
        $net = $this->stockMovements()
            ->selectRaw('COALESCE(SUM(quantity),0) as net')
            ->value('net');

        return (float) $this->opening_stock + (float) $net;
    }
}