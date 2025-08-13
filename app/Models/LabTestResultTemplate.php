<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTestResultTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'input_fields',
        'added_by_id',
        'last_updated_by_id',
    ];

    protected $casts = [
        'input_fields' => 'array',
    ];

    public function categories()
    {
        return $this->hasMany(LabTestTemplateCategory::class, 'template_id');
    }

    public function tables()
    {
        return $this->hasMany(LabTestTemplateTable::class, 'template_id');
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
