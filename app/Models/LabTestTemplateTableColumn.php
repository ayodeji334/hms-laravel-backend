<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTestTemplateTableColumn extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'table_id',
        'header',
        'index',
        'sub_columns'
    ];

    protected $casts = [
        'sub_columns' => 'array',
    ];

    public function table()
    {
        return $this->belongsTo(LabTestTemplateTable::class, 'table_id');
    }
}
