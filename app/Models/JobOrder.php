<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOrder extends Model
{
    use SoftDeletes;

    protected $table = 'job_orders';

    protected $fillable = [
        'job_no', 'vendor_id', 'sale_id', 'job_type',
        'status', 'issue_date', 'remarks', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
    ];

    public function vendor()  { return $this->belongsTo(Vendor::class, 'vendor_id'); }
    public function items()   { return $this->hasMany(JobOrderItem::class, 'job_order_id'); }
    public function receives(){ return $this->hasMany(JobOrderReceive::class, 'job_order_id'); }
}