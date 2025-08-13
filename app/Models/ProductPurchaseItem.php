<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductPurchaseItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'manufacturer_id',
        'purchase_price',
        'number_of_cartons',
        'number_of_packs',
        'total_quantity',
        'deleted_by_id',
        'added_by_id'
    ];

    public function inventoryRecord()
    {
        return $this->belongsTo(ProductPurchase::class, 'purchase_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function manufacturer()
    {
        return $this->belongsTo(ProductManufacturer::class, 'manufacturer_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }
}
