<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * ServiceVendor — pivot model for service_vendor table.
 *
 * WHY a dedicated model:
 *   1. ProjectPhase stores service_vendor_id to lock the agreed rate
 *      at the time of dispatch. Without a model, findOrFail() doesn't work.
 *   2. Allows direct queries: ServiceVendor::where('vendor_id', x)->get()
 *   3. Allows adding casts, accessors, and scopes on pivot data.
 *
 * The relationship is still defined on Service and Vendor models
 * using belongsToMany — this model just adds direct queryability.
 */
class ServiceVendor extends Pivot
{
    // Tell Eloquent the actual table name
    protected $table = 'service_vendor';

    // Pivot models do not use auto-incrementing by default.
    // Our migration adds $table->id() so we enable it.
    public $incrementing = true;

    protected $fillable = [
        'service_id',
        'vendor_id',
        'rate',
        'currency',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    // Project phases that used this service+vendor combination
    public function projectPhases()
    {
        return $this->hasMany(ProjectPhase::class, 'service_vendor_id');
    }

    // ── Scopes ──────────────────────────────────────────────────────

    // All service_vendor rows for a specific vendor
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    // All service_vendor rows for a specific service
    public function scopeForService($query, $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }
}