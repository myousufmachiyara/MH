<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use SoftDeletes;

    protected $table = 'vouchers';

    protected $fillable = [
        'voucher_no', 'type', 'voucher_date', 'narration', 'attachments',
        'reference_type', 'reference_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'attachments'  => 'array',
    ];

    public function entries()
    {
        return $this->hasMany(VoucherEntry::class, 'voucher_id');
    }

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}