<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_no',
        'customer_id',
        'title',
        'customer_po_no',
        'status',
        'order_date',
        'delivery_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'delivery_date' => 'date',
    ];

    // ── Status definitions ───────────────────────────────────────────

    public const STATUSES = [
        'sampling'      => 'Sampling',
        'po_received'   => 'PO Received',
        'in_production' => 'In Production',
        'completed'     => 'Completed',
        'dropped'       => 'Dropped',
    ];

    public const STATUS_BADGES = [
        'sampling'      => 'bg-warning text-dark',
        'po_received'   => 'bg-info text-dark',
        'in_production' => 'bg-primary',
        'completed'     => 'bg-success',
        'dropped'       => 'bg-danger',
    ];

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-secondary';
    }

    // ── Auto-generate project number ─────────────────────────────────

    public static function generateProjectNo(): string
    {
        $year   = date('Y');
        $prefix = 'MH-' . $year . '-';

        $last = static::withTrashed()
            ->where('project_no', 'like', $prefix . '%')
            ->orderByDesc('project_no')
            ->value('project_no');

        $next = $last
            ? (int) substr($last, strlen($prefix)) + 1
            : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function comments()
    {
        return $this->hasMany(ProjectComment::class)->latest();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Relationships added progressively as modules are installed ───
    //
    // SAMPLING MODULE  → uncomment after installing Sampling:
    public function samples()
    {
        return $this->hasMany(Sample::class);
    }
    //
    // PROJECT PHASES MODULE  → uncomment after installing Project Phases:
    public function phases()
    {
        return $this->hasMany(ProjectPhase::class)->orderBy('phase_order');
    }
    //
    // PURCHASE ORDERS MODULE  → uncomment after installing Purchase Orders:
    // public function purchaseOrders()
    // {
    //     return $this->hasMany(PurchaseOrder::class);
    // }
    //
    // SALE INVOICES MODULE  → uncomment after installing Sale Invoices:
    // public function saleInvoices()
    // {
    //     return $this->hasMany(SaleInvoice::class);
    // }
    //
    // COSTING SUMMARY  → uncomment after Phases + Sampling are installed:
    // public function getTotalCostAttribute(): float
    // {
    //     $phaseCost    = $this->phases()->sum('total_cost');
    //     $samplingCost = $this->samples()
    //                         ->where('include_in_project_costing', true)
    //                         ->sum('total_cost');
    //     $materialCost = DB::table('phase_materials')
    //                       ->join('project_phases', 'phase_materials.phase_id', '=', 'project_phases.id')
    //                       ->where('project_phases.project_id', $this->id)
    //                       ->sum('phase_materials.total_cost');
    //     return (float) ($phaseCost + $samplingCost + $materialCost);
    // }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'dropped']);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}