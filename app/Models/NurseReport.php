<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NurseReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'remark',
        'vital_sign_id',
        'admission_id',
        'created_by',
    ];

    public function vitalSign()
    {
        return $this->belongsTo(VitalSign::class, 'vital_sign_id');
    }

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
