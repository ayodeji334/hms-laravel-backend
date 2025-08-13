<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RadiologyResult extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'clinical_diagnosis',
        'part_examined',
        'result_date',
        'size_of_films',
        'test_id',
        'number_of_films',
        'carried_out_by',
        'status',
        'added_by',
        'last_updated_by',
        'request_id',
        'payment_id',
    ];

    protected $casts = [
        'result_date' => 'date',
    ];

    public function test()
    {
        return $this->belongsTo(Service::class, 'test_id');
    }

    public function carriedOutBy()
    {
        return $this->belongsTo(User::class, 'carried_out_by');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function request()
    {
        return $this->belongsTo(RadiologyRequest::class, 'request_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
