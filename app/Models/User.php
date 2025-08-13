<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'staff_id',
        'email',
        'password',
        'phone_number',
        'firstname',
        'lastname',
        'middlename',
        'gender',
        'marital_status',
        'religion',
        'nationality',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean'
        ];
    }

    public function assignedBranch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * The attributes that should be appended when the model is converted to an array or JSON.
     *
     * @var array<int, string>
     */
    protected $appends = ['full_name'];

    /** Accessor to concatenate firstname, middlename, and lastname into a full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        // Capitalize the first letter of firstname, and lastname
        $firstname = ucfirst($this->firstname);
        $lastname = ucfirst($this->lastname);

        // Concatenate with a space between each name, skip middlename if not present
        return trim("{$firstname} {$lastname}");
    }
}
