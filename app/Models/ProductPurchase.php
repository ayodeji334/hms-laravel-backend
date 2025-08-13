<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPurchase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_receipt',
        'purchase_date',
        'total_amount',
        'status',
        'history',
        'added_by_id',
        'last_updated_by_id',
        'approved_by_id',
        'deleted_by_id',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'history' => 'array',
    ];

    public function purchasedItems()
    {
        return $this->hasMany(ProductPurchaseItem::class, 'purchase_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }
}
