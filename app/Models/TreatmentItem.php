<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TreatmentItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ["created_by_id", "treatment_id", "product_id", 'quantity'];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function treatment()
    {
        return $this->belongsTo(Treatment::class, 'treatment_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
