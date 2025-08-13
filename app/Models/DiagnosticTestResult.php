<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DiagnosticTestResult extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'diagnostic_tests';

    protected $fillable = [
        'result_details',
        'result_date',
        'result_carried_out_by_id',
        'request_id',
        'test_id',
        'added_by_id',
        'last_updated_by_id',
        'patient_id',
        "is_save_as_draft",
    ];

    protected $casts = [
        'result_details' => 'array',
        'result_date' => 'date',
        'is_save_as_draft' => 'boolean'
    ];

    public function resultCarriedOutBy()
    {
        return $this->belongsTo(User::class, 'result_carried_out_by_id');
    }

    public function request()
    {
        return $this->belongsTo(LabRequest::class, 'request_id');
    }

    public function test()
    {
        return $this->belongsTo(Service::class, 'test_id');
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
        return $this->belongsTo(Patient::class);
    }
}
