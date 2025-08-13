<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OperationRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'diagnosis_before_operation',
        'post_operation_diagnosis',
        'procedure_carried_out',
        'complications',
        'packs',
        'specimens',
        'operative_findings',
        'anesthesia_type',
        'operation_date',
        'last_updated_by_id',
        'deleted_by_id',
        'added_by_id',
        'anesthetist',
        'surgeon_id',
        'scrub_nurse_id',
        'assistant_surgeon_id',
        'patient_id',
    ];

    protected $casts = [
        'operation_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'assistant_surgeons' => 'array',
        'scrub_nurses' => 'array',
    ];

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function anesthetist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anesthetist_id');
    }

    public function surgeon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surgeon_id');
    }

    public function scrubNurses()
    {
        return $this->belongsToMany(User::class, 'operation_record_scrub_nurses', 'operation_record_id', 'scrub_nurse_id');
    }

    public function assistantSurgeons()
    {
        return $this->belongsToMany(User::class, 'operation_record_assistant_surgeons', 'operation_record_id', 'assistant_surgeon_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
