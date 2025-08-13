<?php

namespace App\Models;

use App\Models\Admission;
use App\Models\Note;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\TreatmentItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Treatment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'treatments';

    protected $fillable = [
        'diagnosis',
        'treatment_date',
        'treatment_end_date',
        'treatment_type',
        'status',
        'patient_id',
        'created_by_id',
        'treated_by_id',
        'last_updated_by_id',
        'admission_id',
        'appointment_id',
    ];

    protected $casts = [
        'treatment_date' => 'date',
        'treatment_end_date' => 'date',
    ];

    // Treatment Statuses
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    /**
     * Relationships
     */

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function treatedBy()
    {
        return $this->belongsTo(User::class, 'treated_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function visitation()
    {
        return $this->belongsTo(Visitation::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function items()
    {
        return $this->hasMany(TreatmentItem::class);
    }

    public function tests()
    {
        return $this->hasMany(LabRequest::class);
    }

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function payments()
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
