<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_debtor' => 'boolean',
        ];
    }

    protected $hidden = ["password"];

    protected $fillable = [
        'email',
        'password',
        'matriculation_number',
        'phone_number',
        'firstname',
        'lastname',
        'middlename',
        'is_admitted',
        'gender',
        'permanent_address',
        'contact_address',
        'next_of_kin_firstname',
        'next_of_kin_lastname',
        'next_of_kin_address',
        'next_of_kin_phone_number',
        'next_of_kin_relationship',
        'state_of_origin',
        'marital_status',
        'place_of_birth',
        'religion',
        'nationality',
        'level',
        'tribe',
        'lga',
        'is_active',
        'age',
        'hall_of_residence',
        'genotype',
        'blood_group',
        'type',
        'staff_number',
        'occupation',
        'last_updated_on',
        'password_changed_on',
        'last_loggedin_on',
        'title',
        'insurance_number',
        'organisation_hmo_id',
        'room_number',
        'department',
        "patient_reg_no",
    ];

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function organisationHmo()
    {
        return $this->belongsTo(OrganisationAndHmo::class);
    }

    public function anteNatalRecords()
    {
        return $this->hasMany(AnteNatal::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class);
    }

    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }

    public function vitalSigns()
    {
        return $this->hasMany(VitalSign::class);
    }

    public function surgicalOperations()
    {
        return $this->hasMany(OperationRecord::class);
    }

    public function physicalExaminations()
    {
        return $this->hasMany(GeneralExamination::class);
    }

    public function visitations()
    {
        return $this->hasMany(Visitation::class);
    }

    public function labRequests()
    {
        return $this->hasMany(LabRequest::class);
    }

    public function familyMembers()
    {
        return $this->hasMany(FamilyRelationship::class, 'sponsor_id');
    }

    public function sponsorOf()
    {
        return $this->hasMany(FamilyRelationship::class, 'patient_id');
    }

    protected $appends = ['fullname'];

    public function getFullNameAttribute()
    {
        $firstname = ucfirst($this->firstname);
        $lastname = ucfirst($this->lastname);
        return trim("{$firstname} {$lastname}");
    }
}
