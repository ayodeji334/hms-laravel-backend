<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTestTemplateTableRowCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'index',
        'table_id'
    ];

    public function table()
    {
        return $this->belongsTo(LabTestTemplateTable::class, 'table_id');
    }

    public function rows()
    {
        return $this->hasMany(LabTestTemplateTableRowCategoryRow::class, 'category_id');
    }
}
