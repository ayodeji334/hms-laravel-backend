<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HmoPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'hmo_id',
        'total_due',
        'amount_paid',
        'outstanding_balance',
        'transaction_reference',
        'payment_date',
        'history',
        'payment_method',
        'added_by_id',
        'last_updated_by_id',
    ];

    protected $casts = [
        'history' => 'array',
        'payment_date' => 'date',
    ];

    public function hmo()
    {
        return $this->belongsTo(OrganisationAndHmo::class);
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
