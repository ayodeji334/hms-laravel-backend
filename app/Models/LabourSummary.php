<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabourSummary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'induction',
        'indication',
        'method_of_delivery',
        'expected_date_delivery',
        'cephalic_presentation',
        'breech_presentation',
        'placenta_membranes',
        'perineum',
        'time_date_of_delivery',
        'number_of_skin_sutures',
        'number_of_blood_loss',
        'malformation',
        'infants_status',
        'infants_sexes',
        'infants_weights',
        'mother_uterus_condition',
        'mother_bladder_condition',
        'mother_blood_pressure',
        'mother_pulse',
        'mother_temperature',
        'mother_rep',
        'treatment',
        'supervisor_id',
        'last_updated_by_id',
        'labour_record_id',
    ];

    protected $casts = [
        'infants_status' => 'array',
        'infants_sexes' => 'array',
        'infants_weights' => 'array',
        'expected_date_delivery' => 'date',
        'time_date_of_delivery' => 'datetime',
    ];

    public function labourRecord()
    {
        return $this->belongsTo(LabourRecord::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }
}
