<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabourGraph extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'maternal_blood_pulse',
        'time',
        'maternal_pulse',
        'position',
        'caput',
        'moulding',
        'fetal_heart_rate',
        'cervical_dilation',
        'liquor',
        'maternal_temperature',
        'fluids_and_drugs',
        'uterine_contractions',
        'oxytocin_administrations',
        'urine_analyses',
        'added_by_id',
        'labour_id',
    ];

    protected $casts = [
        'time' => 'datetime',
        'maternal_temperature' => 'float',
        'fluids_and_drugs' => 'array',
        'uterine_contractions' => 'array',
        'oxytocin_administrations' => 'array',
        'urine_analyses' => 'array',
    ];

    public function labour()
    {
        return $this->belongsTo(LabourRecord::class);
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
