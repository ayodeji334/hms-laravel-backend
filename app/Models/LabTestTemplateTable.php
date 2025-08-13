<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTestTemplateTable extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'index',
        'template_id',
        'category_id'
    ];

    public function template()
    {
        return $this->belongsTo(LabTestResultTemplate::class, 'template_id');
    }

    public function category()
    {
        return $this->belongsTo(LabTestTemplateCategory::class, 'category_id');
    }

    public function columns()
    {
        return $this->hasMany(LabTestTemplateTableColumn::class, 'table_id');
    }

    public function rows()
    {
        return $this->hasMany(LabTestTemplateTableRow::class, 'table_id');
    }

    public function rowCategories()
    {
        return $this->hasMany(LabTestTemplateTableRowCategory::class, 'table_id');
    }
}
