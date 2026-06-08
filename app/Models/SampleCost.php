<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SampleCost extends Model
{
    protected $fillable = [
        'sample_id',
        'description',
        'amount',
        'include_in_project_costing',
        'borne_by',
    ];

    protected $casts = [
        'amount'                     => 'decimal:2',
        'include_in_project_costing' => 'boolean',
    ];

    public function sample()
    {
        return $this->belongsTo(Sample::class);
    }
}