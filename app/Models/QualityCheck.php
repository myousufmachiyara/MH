<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityCheck extends Model
{
    use SoftDeletes;

    protected $table = 'quality_checks';

    protected $fillable = [
        'qc_no', 'job_order_receive_id', 'product_id',
        'quantity_inspected', 'quantity_passed', 'quantity_rejected',
        'rejection_reason', 'qc_date', 'remarks', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity_inspected' => 'decimal:3',
        'quantity_passed'    => 'decimal:3',
        'quantity_rejected'  => 'decimal:3',
        'qc_date'             => 'date',
    ];

    public function jobOrderReceive() { return $this->belongsTo(JobOrderReceive::class, 'job_order_receive_id'); }
    public function product()         { return $this->belongsTo(Product::class, 'product_id'); }
}