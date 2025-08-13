<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalExamination extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'right_eye_vision_acuity_without_glasses',
        'left_eye_vision_acuity_without_glasses',
        'right_eye_vision_acuity_with_glasses',
        'left_eye_vision_acuity_with_glasses',
        'color_vision_test',
        'height',
        'weight',
        'bmi',
        'apex_beat',
        'heart_sound',
        'blood_pressure',
        'pulse',
        'respiratory_inspection',
        'respiratory_palpation',
        'respiratory_percussion',
        'respiratory_auscultation',
        'abdominal_inspection',
        'abdominal_palpation',
        'abdominal_percussion',
        'abdominal_auscultation',
        'rectal_inspection',
        'rectal_palpation',
        'rectal_percussion',
        'rectal_auscultation',
        'genital_inspection',
        'genital_palpation',
        'genital_percussion',
        'genital_auscultation',
        'breast_inspection',
        'breast_palpation',
        'breast_percussion',
        'breast_auscultation',
        'mental_altertness',
        'glasgow_coma_scale',
        'other_examination',
        'recommendation_status',
        'visitation_id',
        'added_by_id',
        'last_updated_by_id',
    ];

    public function visitation()
    {
        return $this->belongsTo(Visitation::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }
}
