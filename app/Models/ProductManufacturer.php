<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductManufacturer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'phone_number',
        'email',
        'added_by_id',
        'last_updated_by_id',
        'deleted_by_id'
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
