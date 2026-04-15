<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrescriptionItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'prescription_id',
        'product_id',
        'dosage',
        'duration',
        'frequency',
        'instruction',
        'dispensed_by_id',
        'history'
    ];

    protected function casts()
    {
        return [
            "history" => "array"
        ];
    }

    public function prescription()
    {
        return $this->belongsTo(Prescription::class, 'prescription_id');
    }

    public function dispensedBy()
    {
        return $this->belongsTo(User::class, 'dispensed_by_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
