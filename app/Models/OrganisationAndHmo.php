<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganisationAndHmo extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone_number', 'contact_address', 'outstanding_balance', 'added_by_id', 'last_updated_by_id', 'history'];

    protected function casts()
    {
        return ['outstanding_balance' => 'integer', 'history' => 'array'];
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
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
