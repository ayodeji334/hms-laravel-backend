<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTestTemplateTableRowCategoryRow extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'values',
        'index',
        'category_id'
    ];

    protected $casts = [
        'values' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(LabTestTemplateTableRowCategory::class, 'category_id');
    }
}
