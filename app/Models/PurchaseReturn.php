<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use SoftDeletes;

    protected $table = 'purchase_returns';

    protected $fillable = [
        'return_no', 'purchase_id', 'vendor_id', 'return_date',
        'subtotal', 'tax_amount', 'total_amount',
        'remarks', 'attachments', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'return_date'   => 'date',
        'subtotal'      => 'decimal:2',
        'tax_amount'    => 'decimal:2',
        'total_amount'  => 'decimal:2',
        'attachments'   => 'array',
    ];

    public function purchase() { return $this->belongsTo(Purchase::class, 'purchase_id'); }
    public function vendor()   { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function items()    { return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id'); }
}