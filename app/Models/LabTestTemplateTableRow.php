<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTestTemplateTableRow extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'values',
        'index',
        'table_id',
        'category_id'
    ];

    protected $casts = [
        'values' => 'array',
    ];

    public function table()
    {
        return $this->belongsTo(LabTestTemplateTable::class, 'table_id');
    }

    public function category()
    {
        return $this->belongsTo(LabTestTemplateTableRowCategory::class, 'category_id');
    }
}
