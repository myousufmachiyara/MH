<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use SoftDeletes;

    protected $table = 'purchases';

    protected $fillable = [
        'purchase_no', 'vendor_id', 'order_id', 'purchase_date',
        'subtotal', 'tax_amount', 'total_amount', 'status', 'remarks',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal'      => 'decimal:2',
        'tax_amount'    => 'decimal:2',
        'total_amount'  => 'decimal:2',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function order()  { return $this->belongsTo(Order::class, 'order_id'); }
    public function items()  { return $this->hasMany(PurchaseItem::class, 'purchase_id'); }
}