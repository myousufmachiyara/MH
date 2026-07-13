<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobOrderItem extends Model
{
    protected $table = 'job_order_items';

    protected $fillable = ['job_order_id', 'product_id', 'quantity', 'source_status'];

    protected $casts = ['quantity' => 'decimal:3'];

    public function jobOrder() { return $this->belongsTo(JobOrder::class, 'job_order_id'); }
    public function product()  { return $this->belongsTo(Product::class, 'product_id'); }
}