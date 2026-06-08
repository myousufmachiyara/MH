<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhaseMaterial extends Model
{
    protected $fillable = [
        'phase_id',
        'product_id',
        'quantity',
        'rate',
        'total_cost',
        'notes',
    ];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'rate'       => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function phase()
    {
        return $this->belongsTo(ProjectPhase::class, 'phase_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}