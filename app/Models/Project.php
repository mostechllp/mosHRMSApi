<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_name',
        'description',
        'client_name',
        'client_contact',
        'department_id',
        'start_date',
        'end_date',
        'website_live_date',
        'client_contacted_date',
        'domain_name',
        'domain_purchased_date',
        'website_url',
        'domain_expiry_date',
        'domain_purchased_from',
        'is_email_purchased',
        'status',
        'project_manager_id',
        'team_lead_id',
        'created_by',
        'deleted_by',
    ];

    public function emails()
    {
        return $this->hasMany(ProjectEmail::class);
    }

    public function projectManager()
    {
        return $this->belongsTo(Employee::class, 'project_manager_id');
    }

    public function teamLead()
    {
        return $this->belongsTo(Employee::class, 'team_lead_id');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_project')
            ->using(ProjectAssignment::class)
            ->withPivot('assigned_by', 'deleted_by', 'deleted_at')
            ->wherePivot('deleted_at', null);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
