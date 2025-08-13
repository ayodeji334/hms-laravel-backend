<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'type',
        'is_available',
        'created_by_id',
        'last_updated_by_id',
        'physical_examination_id',
        'result_template_id',
        'appointment_id',
    ];

    // const TYPES = ['LAB_TEST', 'SURGERY', 'CONSULTATION', 'OTHER']; 

    /**
     * The categories the service belongs to.
     */
    public function categories()
    {
        return $this->belongsToMany(ServiceCategory::class, 'service_category_service', 'service_id', 'service_category_id');
    }

    public function visitations()
    {
        return $this->belongsToMany(Visitation::class, 'service_visitation', 'service_id', 'visitation_id');
    }

    // /**
    //  * Physical examination associated with this service.
    //  */
    // public function physicalExamination()
    // {
    //     return $this->belongsTo(PhysicalExamination::class);
    // }

    /**
     * Lab test template associated with this service.
     */
    public function resultTemplate()
    {
        return $this->belongsTo(LabTestResultTemplate::class);
    }

    /**
     * Appointment associated with this service.
     */
    public function appointment()
    {
        return $this->belongsTo(Visitation::class);
    }

    /**
     * Creator of the service.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Last person who updated the service.
     */
    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    // /**
    //  * Lab requests associated with this service.
    //  */
    // public function requests()
    // {
    //     return $this->hasMany(LabRequest::class);
    // }
}
