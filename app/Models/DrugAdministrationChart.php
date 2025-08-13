<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DrugAdministrationChart extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'procedure',
        'time',
        'date',
        'dosage',
        'day',
        'date',
        'admission_id',
        'added_by_id',
        'last_updated_by_id',
    ];

    protected $casts = [
        'time' => 'string',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class);
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
