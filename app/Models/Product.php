<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tracking_code',
        'brand_name',
        'generic_name',
        'description',
        'sales_price',
        'unit_price',
        'purchase_price',
        'batch_code',
        'dosage_strength',
        'dosage_type',
        'nafdac_code',
        'weight',
        'is_prescription_required',
        'manufacturer_id',
        'manufacturing_date',
        'storage_condition',
        'quantity_available_for_sales',
        'expiry_date',
        'added_by_id',
        'last_updated_by_id',
        'deleted_by_id',
        'stock_alert_threshold',
        'audit_log'
    ];

    public function manufacturer()
    {
        return $this->belongsTo(ProductManufacturer::class, 'manufacturer_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function type()
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }
}
