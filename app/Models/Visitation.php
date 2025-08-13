<?php

namespace App\Models;

use App\Enums\VisitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visitation extends Model
{
    use SoftDeletes;
    //
    protected $fillable = [
        'history',
        'end_time',
        'start_time',
        'type',
        'start_date',
        'patient_id',
        'payable_id'
    ];

    protected function casts(): array
    {
        return [
            'history' => 'array',
            'not_available_tests' => 'array',
            // 'status' => VisitationStatus::class
        ];
    }

    public function payment()
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedDoctor()
    {
        return $this->belongsTo(User::class);
    }

    public function treatment()
    {
        return $this->hasMany(Treatment::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function physicalExaminations()
    {
        return $this->hasMany(PhysicalExamination::class);
    }

    public function recommendedTests()
    {
        return $this->belongsToMany(LabRequest::class, 'service_visitation', 'visitation_id', 'service_id');
    }
}
