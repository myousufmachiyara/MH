<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sample extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'sample_no',
        'status',
        'include_in_project_costing',
        'courier_name',
        'tracking_no',
        'dispatched_at',
        'received_at',
        'rejection_reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'include_in_project_costing' => 'boolean',
        'dispatched_at'              => 'date',
        'received_at'                => 'date',
    ];

    // ── Status definitions ───────────────────────────────────────────

    public const STATUSES = [
        'pending'   => 'Pending',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'resampled' => 'Resampled',
        'dropped'   => 'Dropped',
    ];

    public const STATUS_BADGES = [
        'pending'   => 'bg-warning text-dark',
        'approved'  => 'bg-success',
        'rejected'  => 'bg-danger',
        'resampled' => 'bg-info text-dark',
        'dropped'   => 'bg-secondary',
    ];

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary';
    }

    // ── Auto-generate sample number ──────────────────────────────────

    public static function generateSampleNo(Project $project): string
    {
        $prefix = 'SMP-' . $project->project_no . '-';

        $last = static::withTrashed()
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->value('sample_no');

        $next = $last
            ? (int) substr($last, strlen($prefix)) + 1
            : 1;

        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    // ── Relationships ────────────────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function costs()
    {
        return $this->hasMany(SampleCost::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Computed ─────────────────────────────────────────────────────

    public function getTotalCostAttribute(): float
    {
        return (float) $this->costs->sum('amount');
    }

    public function getProjectCostAttribute(): float
    {
        return (float) $this->costs
            ->where('include_in_project_costing', true)
            ->sum('amount');
    }
}