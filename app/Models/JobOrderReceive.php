<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOrderReceive extends Model
{
    use SoftDeletes;

    protected $table = 'job_order_receives';

    protected $fillable = [
        'receive_no', 'job_order_id', 'receive_date',
        'processing_charge', 'remarks', 'created_by', 'updated_by',
    ];
    
    protected $casts = [
        'receive_date'       => 'date',
        'processing_charge'  => 'decimal:2',
        'attachments'        => 'array',
    ];
    public function jobOrder() { return $this->belongsTo(JobOrder::class, 'job_order_id'); }
    public function items()    { return $this->hasMany(JobOrderReceiveItem::class, 'job_order_receive_id'); }
    public function outputs() { return $this->hasMany(JobOrderReceiveOutput::class, 'job_order_receive_id'); }
}