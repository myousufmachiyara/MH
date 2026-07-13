<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorStockLedger extends Model
{
    protected $table = 'vendor_stock_ledger';

    protected $fillable = [
        'doc_no', 'vendor_id', 'product_id', 'status', 'quantity',
        'reference_type', 'reference_id', 'entry_date', 'remarks', 'created_by',
    ];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'entry_date' => 'date',
    ];

    public const STATUS_FRESH    = 'fresh';
    public const STATUS_ISSUED   = 'issued';
    public const STATUS_LEFTOVER = 'leftover';

    public function vendor()  { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function product() { return $this->belongsTo(Product::class, 'product_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeGatePasses($q) { return $q->where('reference_type', 'GatePass'); }

    // Balance of a given status pool for a vendor+product
    public static function balance(int $vendorId, int $productId, string $status): float
    {
        return (float) self::where('vendor_id', $vendorId)
            ->where('product_id', $productId)
            ->where('status', $status)
            ->sum('quantity');
    }
}