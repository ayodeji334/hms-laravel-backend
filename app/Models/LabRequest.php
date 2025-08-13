<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LabRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'is_approval_required',
        'priority',
        'is_patient',
        'sample_collected_date',
        'require_sample_collection',
        'sample_label',
        'sample_type',
        'request_date',
        'approved_date',
        'customer_name',
        'patient_id',
        'service_id',
        'payment_id',
        'treatment_id',
        'approved_by_id',
        'added_by_id',
        'last_updated_by_id',
        'test_result_id',
    ];

    protected $casts = [
        'is_approval_required' => 'boolean',
        'is_patient' => 'boolean',
        'require_sample_collection' => 'boolean',
        'sample_collected_date' => 'datetime',
        'request_date' => 'date',
        'approved_date' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

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

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function testResult()
    {
        return $this->hasOne(DiagnosticTestResult::class, 'request_id');
    }
}
