<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobType extends Model
{
    protected $fillable = ['name', 'service_cost_account_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function serviceCostAccount() { return $this->belongsTo(ChartOfAccounts::class, 'service_cost_account_id'); }
    public function jobOrders() { return $this->hasMany(JobOrder::class, 'job_type_id'); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}