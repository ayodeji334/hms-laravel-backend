<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreviousPregnanciesSummary extends Model
{
    use SoftDeletes;

    protected $table = 'previous_pregnancies_summaries';

    protected $fillable = [
        'date_of_birth',
        'duration_of_pregnancy',
        'child_weight',
        'child_gender',
        'complication_during_pregnancy',
        'complication_during_labour',
        'pueperium',
        'is_child_still_alive',
        'cause_of_death',
        'child_age_before_death',
        'ante_natal_record_id',
        'last_updated_by_id',
    ];

    public function anteNatalRecord()
    {
        return $this->belongsTo(AnteNatal::class, 'ante_natal_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }
}
