<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductSales extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'history',
        'total_price',
        'invoice_id',
        'total_discount_price',
        'customer_name',
        'status',
        'type',
        'payment_id',
        'prescription_id',
        'sold_by_id',
        'deleted_by_id',
        'confirmed_by_id',
        'last_updated_by_id'
    ];

    protected function casts()
    {
        return ['history' => 'array'];
    }


    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function payment()
    {
        return $this->morphOne(Payment::class, "payable");
    }

    public function soldBy()
    {
        return $this->belongsTo(User::class, 'sold_by_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }

    public function salesItems()
    {
        return $this->hasMany(ProductSalesItem::class, 'product_sales_id');
    }
}
