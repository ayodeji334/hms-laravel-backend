<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prescription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'status',
        'last_declined_on',
        'last_approved_on',
        'history_logs',
        'treatment_id',
        'ante_natal_id',
        'requested_by_id',
        'last_approved_by_id',
        'last_declined_by_id',
        'patient_id',
        'visitation_id',
        'admission_id'
    ];

    protected $casts = [
        'history_logs' => 'array',
    ];

    public function notes()
    {
        return $this->hasMany(Note::class);
    }

    public function items()
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class);
    }

    public function salesRecord()
    {
        return $this->hasOne(ProductSales::class, "prescription_id");
    }

    public function anteNatal()
    {
        return $this->belongsTo(AnteNatal::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function lastApprovedBy()
    {
        return $this->belongsTo(User::class, 'last_approved_by_id');
    }

    public function lastDeclinedBy()
    {
        return $this->belongsTo(User::class, 'last_declined_by_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function visitation()
    {
        return $this->belongsTo(Visitation::class);
    }
}
