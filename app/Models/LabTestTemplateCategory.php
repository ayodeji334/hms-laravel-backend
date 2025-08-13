<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTestTemplateCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'index',
        'name',
        'input_fields',
        'template_id'
    ];

    protected $casts = [
        'input_fields' => 'array',
    ];

    public function template()
    {
        return $this->belongsTo(LabTestResultTemplate::class, 'template_id');
    }

    public function tables()
    {
        return $this->hasMany(LabTestTemplateTable::class, 'category_id');
    }
}
