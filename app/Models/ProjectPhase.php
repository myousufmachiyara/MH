<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectPhase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'service_vendor_id',
        'phase_order',
        'rate',
        'quantity_dispatched',
        'dispatched_at',
        'quantity_received',
        'quantity_rejected',
        'received_at',
        'status',
        'rejection_reason',
        'notes',
        'total_cost',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rate'                => 'decimal:2',
        'quantity_dispatched' => 'decimal:3',
        'quantity_received'   => 'decimal:3',
        'quantity_rejected'   => 'decimal:3',
        'total_cost'          => 'decimal:2',
        'dispatched_at'       => 'date',
        'received_at'         => 'date',
    ];

    // ── Status definitions ───────────────────────────────────────────

    public const STATUSES = [
        'pending'            => 'Pending',
        'dispatched'         => 'Dispatched',
        'partially_received' => 'Partially Received',
        'fully_received'     => 'Fully Received',
        'approved'           => 'Approved',
        'rejected'           => 'Rejected',
    ];

    public const STATUS_BADGES = [
        'pending'            => 'bg-secondary',
        'dispatched'         => 'bg-primary',
        'partially_received' => 'bg-warning text-dark',
        'fully_received'     => 'bg-info text-dark',
        'approved'           => 'bg-success',
        'rejected'           => 'bg-danger',
    ];

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary';
    }

    // ── Relationships ────────────────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The locked service+vendor pivot record.
     * Gives access to: service, vendor, agreed rate at time of setup.
     */
    public function serviceVendor()
    {
        return $this->belongsTo(ServiceVendor::class, 'service_vendor_id');
    }

    public function materials()
    {
        return $this->hasMany(PhaseMaterial::class, 'phase_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Helpers ──────────────────────────────────────────────────────

    /**
     * Recalculate and save total_cost = quantity_received * rate.
     * Called after any dispatch/receive update.
     */
    public function recalcCost(): void
    {
        $this->total_cost = round($this->quantity_received * $this->rate, 2);
        $this->saveQuietly();
    }

    /**
     * Quantity still outstanding (dispatched but not yet received).
     */
    public function getPendingQuantityAttribute(): float
    {
        return max(0, (float)$this->quantity_dispatched - (float)$this->quantity_received - (float)$this->quantity_rejected);
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }
}