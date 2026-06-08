<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'selling_price',
        'stock_quantity',
    ];

    protected $casts = [
        'selling_price'  => 'decimal:2',
        'stock_quantity' => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Attribute values linked to this variation (Color=Red, Size=M etc.)
     * Pivot table: product_variation_attribute_values
     */
    public function attributeValues()
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_variation_attribute_values',
            'product_variation_id',
            'attribute_value_id'
        )->withTimestamps();
    }

    // Direct pivot rows — used when you need extra pivot columns in future
    public function variationAttributeValues()
    {
        return $this->hasMany(ProductVariationAttributeValue::class, 'product_variation_id');
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'variation_id');
    }

    public function saleItems()
    {
        return $this->hasMany(SaleInvoiceItem::class, 'variation_id');
    }

    // ── Helpers used by new modules ──────────────────────────────────

    /**
     * Human-readable variation label built from attribute values.
     * e.g. "Red - XL"
     * Used in purchase order and project phase dropdowns.
     */
    public function getlabelAttribute(): string
    {
        $parts = $this->attributeValues->map(fn($v) => $v->value)->implode(' - ');
        return $parts ?: $this->sku;
    }
}