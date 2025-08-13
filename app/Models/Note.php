<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Note extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ante_natal_id',
        'treatment_id',
        'content',
        'created_by_id',
        'last_updated_by_id',
        'deleted_by_id',
        'noteable_id',
        'noteable_type',
        'title'
    ];

    public function noteable()
    {
        return $this->morphTo();
    }

    public function anteNatalRecord()
    {
        return $this->belongsTo(AnteNatal::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }
}
