<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'created_by_id',
        'last_updated_by_id',
        'last_deleted_by_id',
    ];

    /**
     * The services that belong to the category.
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_category_service', 'service_category_id', 'service_id');
    }

    /**
     * Creator of the category.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Last person who updated the category.
     */
    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    /**
     * Last person who deleted the category.
     */
    public function lastDeletedBy()
    {
        return $this->belongsTo(User::class, 'last_deleted_by_id');
    }
}
