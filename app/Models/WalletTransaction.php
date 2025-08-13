<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'payment_id',
        'transaction_type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'meta'
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, "payment_id");
    }
}
