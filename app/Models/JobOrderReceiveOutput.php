<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobOrderReceiveOutput extends Model
{
    protected $table = 'job_order_receive_outputs';

    protected $fillable = [
        'job_order_receive_id', 'output_product_id', 'quantity_output', 'conversion_rate', 'processing_amount',
    ];

    protected $casts = [
        'quantity_output'   => 'decimal:3',
        'conversion_rate'   => 'decimal:2',
        'processing_amount' => 'decimal:2',
    ];

    public function jobOrderReceive() { return $this->belongsTo(JobOrderReceive::class, 'job_order_receive_id'); }
    public function outputProduct()   { return $this->belongsTo(Product::class, 'output_product_id'); }
}