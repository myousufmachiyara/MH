<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobOrderComment extends Model
{
    protected $table = 'job_order_comments';

    protected $fillable = ['job_order_id', 'user_id', 'comment'];

    public function jobOrder() { return $this->belongsTo(JobOrder::class, 'job_order_id'); }
    public function user()     { return $this->belongsTo(User::class, 'user_id'); }
}