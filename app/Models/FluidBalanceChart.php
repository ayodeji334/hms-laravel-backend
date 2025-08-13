<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FluidBalanceChart extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'input_type',
        'input_tube_volume',
        'input_oral_volume',
        'input_iv_volume',
        'input_total',
        'output_type',
        'output_faeces_volume',
        'output_urine_volume',
        'output_vomit_volume',
        'output_total',
        'time',
        'instructions',
        'admission_id',
        'added_by',
        'last_updated_by',
    ];

    protected $casts = [
        'time' => 'string',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }
}
