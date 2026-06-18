<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RadiologyRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'clinical_diagnosis',
        'part_examined',
        'request_date',
        'size_of_films',
        'service_id',        // fixed: was 'test_id' — relationship uses service_id
        'number_of_films',
        'status',
        'added_by_id',       // fixed: was 'added_by'
        'last_updated_by_id', // fixed: was 'last_updated_by'
        'carried_out_by_id', // fixed: was 'carried_out_by'
        'payment_id',
        'patient_id',
        'treatment_id',      // added: used in createRecommendedTests
        'customer_name',     // added: used in createRecommendedTests
        'is_patient',        // added: used in createRecommendedTests
        'is_urgent',         // added: radiology urgency flag
    ];

    protected $casts = [
        'request_date' => 'date',
        'is_patient'   => 'boolean',
        'is_urgent'    => 'boolean',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function payment()
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function carriedOutBy()
    {
        return $this->belongsTo(User::class, 'carried_out_by_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id'); // fixed: was empty string
    }

    public function diagnosticResults()
    {
        return $this->morphMany(DiagnosticTestResult::class, 'requestable');
    }
}
