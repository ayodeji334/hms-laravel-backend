<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        "amount",
        'transaction_reference',
        'reference',
        'bank_transfer_to',
        'is_confirmed',
        'payment_method',
        'type',
        'remark',
        'status',
        'history',
        'customer_name',
        'amount_payable',
        'payable',
        "payable_id",
        "patient_id",
        'payable_type',
        "parent_id"
    ];

    protected function casts()
    {
        return [
            'is_confirmed' => 'boolean',
            'amount' => 'integer',
            'amount_payable' => 'integer',
            'history' => 'array'
        ];
    }

    public function payable()
    {
        return $this->morphTo();
    }

    public function labRequest()
    {
        return $this->belongsTo(LabRequest::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function anteNatal()
    {
        return $this->belongsTo(AnteNatal::class, 'registration_payment_id');
    }

    public function productSales()
    {
        return $this->belongsTo(ProductSales::class, 'product_sales_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by_id');
    }

    public function organisation()
    {
        return $this->belongsTo(OrganisationAndHmo::class, 'organisation_id');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }
}
