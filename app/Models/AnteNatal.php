<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnteNatal extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'patient_id',
        'status',
        'duration_of_pregnancy_at_registration',
        'care_id',
        'age_at_marriage',
        'expected_date_delivery',
        'booking_date',
        'last_menstrual_period',
        'total_number_of_children',
        'total_number_of_children_alive',
        'has_heart_disease',
        'has_undergo_operations',
        'has_kidney_disease',
        'has_chest_disease',
        'has_leprosy_disease',
        'pregnancy_history',
        'urinary_symptoms',
        'bleeding',
        'vomitting',
        'discharge',
        'other_symptoms',
        'general_condition',
        'oedema',
        'anaemia',
        'respiratory_system',
        'cardiovascular_system',
        'abdomen',
        'spleen',
        'liver',
        'preliminary_pelvic_assessment',
        'other_abnormalities',
        'weight',
        'ankles_swelling',
        'blood_pressure',
        'height',
        'urine_albumin',
        'urine_sugar',
        'breast_and_nipples',
        'pcv',
        'genotype',
        'blood_group',
        'vdrl',
        'rh',
        'last_updated_by_id',
        'added_by_id'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function payment()
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function previousPregnancies()
    {
        return $this->hasMany(PreviousPregnanciesSummary::class);
    }

    public function registrationPayment()
    {
        return $this->hasOne(Payment::class, "ante_natal_id");
    }

    public function scanReports()
    {
        return $this->hasMany(Note::class);
    }

    public function routineCheckup()
    {
        return $this->hasMany(AnteNatalRoutineAssessment::class);
    }
}
