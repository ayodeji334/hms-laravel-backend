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
        'result_date',
        'size_of_films',
        'test_id',
        'number_of_films',
        'carried_out_by',
        'status',
        'added_by',
        'last_updated_by',
        'request_id',
        'payment_id',
    ];

    protected $casts = [
        'result_date' => 'date',
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
        return $this->belongsTo(Patient::class);
    }

    public function testResult()
    {
        return $this->hasOne(DiagnosticTestResult::class, 'request_id');
    }
}
