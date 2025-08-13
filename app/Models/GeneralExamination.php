<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralExamination extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'is_admitted_before',
        'is_admitted_before_remark',
        'is_undergo_surgical_operation_before',
        'is_undergo_surgical_operation_before_remark',
        'is_presently_on_medication_or_treatment',
        'is_presently_on_medication_or_treatment_remark',
        'is_suffer_mental_illness_before',
        'is_suffer_mental_illness_before_remark',
        'is_suffer_asthma_or_breathlessness_before',
        'is_suffer_asthma_or_breathlessness_before_remark',
        'is_suffer_deafness_or_ear_discharge_before',
        'is_suffer_deafness_or_ear_discharge_before_remark',
        'is_suffer_sleep_disturbance_before',
        'is_suffer_sleep_disturbance_before_remark',
        'is_suffer_abnormal_bleeding_before',
        'is_suffer_abnormal_bleeding_before_remark',
        'is_suffer_fainting_attacks_or_griddiness_before',
        'is_suffer_fainting_attacks_or_griddiness_before_remark',
        'is_suffer_epilepsy_or_fits_before',
        'is_suffer_epilepsy_or_fits_before_remark',
        'is_suffer_recurrent_headaches_or_migraine_before',
        'is_suffer_recurrent_headaches_or_migraine_before_remark',
        'is_suffer_diabetes_mellitus_before',
        'is_suffer_diabetes_mellitus_before_remark',
        'is_suffer_jaundice_before',
        'is_suffer_jaundice_before_remark',
        'is_suffer_sickle_cells_disease_before',
        'is_suffer_sickle_cells_disease_before_remark',
        'is_suffer_skin_disorder_before',
        'is_suffer_skin_disorder_before_remark',
        'is_suffer_recurrent_indigestion_before',
        'is_suffer_recurrent_indigestion_before_remark',
        'is_suffer_tuberculosis_before',
        'is_suffer_tuberculosis_before_remark',
        'is_suffer_congenital_deformity_before',
        'is_suffer_congenital_deformity_before_remark',
        'is_suffer_foot_knee_back_neck_trouble_before',
        'is_suffer_foot_knee_back_neck_trouble_before_remark',
        'is_suffer_allergy_before',
        'is_suffer_allergy_before_remark',
        'allergies',
        'family_sickness_history',
        'immunized_against_diseases',
        'patient_id',
        'added_by_id',
        'last_updated_by_id'
    ];

    protected $casts = [
        'is_admitted_before' => 'boolean',
        'is_undergo_surgical_operation_before' => 'boolean',
        'is_presently_on_medication_or_treatment' => 'boolean',
        'is_suffer_asthma_or_breathlessness_before' => 'boolean',
        'is_suffer_deafness_or_ear_discharge_before' => 'boolean',
        'is_suffer_sleep_disturbance_before' => 'boolean',
        'is_suffer_abnormal_bleeding_before' => 'boolean',
        'is_suffer_fainting_attacks_or_griddiness_before' => 'boolean',
        'is_suffer_epilepsy_or_fits_before' => 'boolean',
        'is_suffer_recurrent_headaches_or_migraine_before' => 'boolean',
        'is_suffer_diabetes_mellitus_before' => 'boolean',
        'is_suffer_jaundice_before' => 'boolean',
        'is_suffer_sickle_cells_disease_before' => 'boolean',
        'is_suffer_skin_disorder_before' => 'boolean',
        'is_suffer_recurrent_indigestion_before' => 'boolean',
        'is_suffer_tuberculosis_before' => 'boolean',
        'is_suffer_congenital_deformity_before' => 'boolean',
        'is_suffer_foot_knee_back_neck_trouble_before' => 'boolean',
        'is_suffer_allergy_before' => 'boolean',
        'allergies' => 'array',
        'family_sickness_history' => 'array',
        'immunized_against_diseases' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }
}
