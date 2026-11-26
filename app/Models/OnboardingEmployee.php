<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingEmployee extends Model
{
    protected $table = 'onboarding_employees';

    protected $fillable = [
        'onboarding_id',
        'first_name',
        'last_name',
        'personal_email',
        'company_email',
        'personal_number',
        'nationality',
        'address',
        'joining_date',
        'dob',
        'gender',
        'marital_status',
        'experience_level',
        'key_skills',
        'highest_education',
        'department_id',
        'designation_id',
        'role_id',
        'company_id',
        'organization_id',
        'verification_data',
        'salary_data',
        'bank_data',
        'checklist_data',
        'resume_path',
        'status',
        'activated_at',
        'user_id',
        'employee_id',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'dob' => 'date',
        'activated_at' => 'datetime',
        'verification_data' => 'array',
        'salary_data' => 'array',
        'bank_data' => 'array',
        'checklist_data' => 'array',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
