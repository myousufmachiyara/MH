<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'order_no', 'vendor_id', 'order_date', 'expected_date',
        'status', 'remarks', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'expected_date' => 'date',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function items()  { return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id'); }
    public function purchases() { return $this->hasMany(Purchase::class, 'order_id'); }
}