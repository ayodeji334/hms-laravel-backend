<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'created_by_id',
        'last_updated_by_id',
        'last_deleted_by_id'
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by_id');
    }

    public function lastDeletedBy()
    {
        return $this->belongsTo(User::class, 'last_deleted_by_id');
    }
}
