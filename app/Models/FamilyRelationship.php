<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FamilyRelationship extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'sponsor_id',
        'relationship_type',
        'billing_responsibility',
    ];

    protected $casts = [
        'billing_responsibility' => 'boolean',
    ];

    /**
     * Get the patient associated with the family relationship.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the sponsor associated with the family relationship.
     */
    public function sponsor()
    {
        return $this->belongsTo(Patient::class, 'sponsor_id');
    }
}
