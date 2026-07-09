<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'order_no', 'customer_id', 'order_date', 'title',
        'status', 'remarks', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'order_date' => 'date',
    ];

    public function customer()    { return $this->belongsTo(Customer::class, 'customer_id'); }
    public function jobOrders()   { return $this->hasMany(JobOrder::class, 'order_id'); }
    public function purchases()   { return $this->hasMany(Purchase::class, 'order_id'); }
    public function sales()       { return $this->hasMany(Sale::class, 'order_id'); }

    public function scopeStatus($q, string $status) { return $q->where('status', $status); }
}