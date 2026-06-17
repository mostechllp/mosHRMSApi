<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskReport extends Model
{
    protected $fillable = ['employee_id', 'date', 'tasks_completed', 'pending_tasks', 'plan_tomorrow', 'remarks'];

    public function user()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
