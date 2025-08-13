<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'admission_date',
        'discharge_date',
        'patient_insurance_detail',
        'type',
        'status',
        'admitted_by_id',
        'discharged_by_id',
        'patient_id',
        'added_by_id',
        'last_updated_by_id',
        'last_deleted_by_id',
        'bed_id',
        'notes',
        'diagnosis',
    ];

    protected $casts = [
        'drug_charts_detail' => 'array',
        'temperature_charts' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function admittedBy()
    {
        return $this->belongsTo(User::class, 'admitted_by_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function dischargedBy()
    {
        return $this->belongsTo(User::class, 'discharged_by_id');
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }

    public function fluidBalanceCharts()
    {
        return $this->hasMany(FluidBalanceChart::class);
    }

    public function drugAdministrationCharts()
    {
        return $this->hasMany(DrugAdministrationChart::class);
    }

    public function nurseReports()
    {
        return $this->hasMany(NurseReport::class);
    }

    public function doctorReports()
    {
        return $this->hasMany(DoctorReport::class);
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class);
    }

    public function investigations()
    {
        return $this->morphMany(Note::class, 'noteable');
    }
}
