<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnteNatalRoutineAssessment extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'gestational_age',
        'risk',
        'comment',
        'date',
        'height_of_fundus',
        'presentation_and_position',
        'presenting_part_to_brim',
        'foetal_heart',
        'urine',
        'blood_pressure',
        'weight',
        'pcv',
        'oedemia',
        'remarks',
        'examiner_id',
        'last_updated_by_id',
        'ante_natal_id',
        'added_by_id',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // Relationships
    public function examiner()
    {
        return $this->belongsTo(User::class, 'examiner_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function anteNatal()
    {
        return $this->belongsTo(AnteNatal::class, 'ante_natal_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }
}
