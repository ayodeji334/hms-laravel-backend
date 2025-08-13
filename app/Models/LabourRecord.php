<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabourRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'estimated_gestational_age',
        'expected_date_delivery',
        'last_menstrual_period',
        'general_condition',
        'abdomen_fundal_height',
        'abdomen_fundal_lie',
        'abdomen_fundal_position',
        'abdomen_fundal_descent',
        'abdomen_fundal_presentation',
        'foetal_heart_rate',
        'vulva_status',
        'vagina_status',
        'vagina_membranes',
        'cervix_percent',
        'cervix_centimeter',
        'pelvis_sacral_curve',
        'placenta_pervia_position',
        'placenta_pervia_current_station',
        'pelvis_conjugate_diameter',
        'pelvis_centimeter',
        'caput',
        'moulding',
        'patient_id',
        'examiner_id',
        'last_updated_by_id',
        'added_by_id',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function examiner()
    {
        return $this->belongsTo(User::class, 'examiner_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function summary()
    {
        return $this->hasOne(LabourSummary::class, 'labour_record_id');
    }

    public function progressions()
    {
        return $this->hasMany(LabourGraph::class, 'labour_id');
    }
}
