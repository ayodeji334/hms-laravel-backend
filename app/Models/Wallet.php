<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'deposit_balance',
        'outstanding_balance'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
