<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTimeLog extends Model
{
    protected $fillable = [
        'employee_id',
        'project_id',
        'date',
        'time_taken_minutes'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
