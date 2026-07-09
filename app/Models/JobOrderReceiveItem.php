<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobOrderReceiveItem extends Model
{
    protected $table = 'job_order_receive_items';

    protected $fillable = [
        'job_order_receive_id', 'product_id',
        'quantity_received', 'quantity_wastage',
    ];

    public function jobOrderReceive() { return $this->belongsTo(JobOrderReceive::class, 'job_order_receive_id'); }
    public function product()          { return $this->belongsTo(Product::class, 'product_id'); }
}