<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'remark',
        'admission_id',
        'created_by',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
