<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganisationAndHmoPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hmo_id',
        'added_by_id',
        'last_updated_by_id',
        'total_due',
        'amount_paid',
        'outstanding_balance',
        'transaction_reference',
        'payment_date',
        'history',
        'payment_method',
        'reference',
    ];

    protected $casts = [
        'history' => 'array',
        'payment_date' => 'date',
    ];

    public function hmo()
    {
        return $this->belongsTo(OrganisationAndHmo::class, 'hmo_id');
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
