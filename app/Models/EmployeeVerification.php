<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'linkedin_url',
        'github_url',
        'portfolio_url',
        'other_professional_url',
        'identity_verified',
        'credentials_verified',
        'employment_info_verified',
        'verification_notes',
    ];

    protected $casts = [
        'identity_verified' => 'boolean',
        'credentials_verified' => 'boolean',
        'employment_info_verified' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
