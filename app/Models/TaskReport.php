<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TaskReport extends Model
{
    protected $fillable = ['employee_id', 'date', 'tasks_completed', 'pending_tasks', 'plan_tomorrow', 'remarks'];

    /**
     * Relationship to the User (employee_id stores users.id)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Relationship to the Employee profile through User
     */
    public function employee()
    {
        return $this->hasOneThrough(
            Employee::class,
            User::class,
            'id',       // users.id
            'user_id',  // employees.user_id
            'employee_id', // task_reports.employee_id
            'id'        // users.id
        );
    }
}
