<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    /** @use HasFactory<\Database\Factories\ActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'activity_type',
        'priority',
        'related_record_type',
        'related_record_id',
        'assigned_employee_id',
        'status',
        'start_date_time',
        'end_date_time',
        'reminder',
        'description',
    ];

    public function assignedEmployee()
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }
}
