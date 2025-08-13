<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quantity',
        'product_id',
        'transaction_type',
        'destination',
        'remarks',
        'added_by_id',
        'last_updated_by_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }
}
