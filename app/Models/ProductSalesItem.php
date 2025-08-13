<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSalesItem extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function payment()
    {
        return $this->morphOne(Payment::class, 'payable_id');
    }
}
