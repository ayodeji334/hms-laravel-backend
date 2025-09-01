<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'is_available',
        'branch_id',
        'category_id',
        'created_by_id',
        'last_updated_by_id',
        'last_deleted_by_id'
    ];

    protected function casts()
    {
        return ['is_available' => 'boolean'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(RoomCategory::class, "room_category_id");
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

    public function beds()
    {
        return $this->hasMany(Bed::class);
    }
}
