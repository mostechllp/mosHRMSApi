<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'reason',
        'duration_days',
        'claim_salary',
        'document',
        'status',
        'approved_by',
        'admin_remark',
        'session1',
        'session2'
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        if (isset($attributes['start_date']) && $this->start_date) {
            $attributes['start_date'] = $this->start_date->format('Y-m-d');
        }
        if (isset($attributes['end_date']) && $this->end_date) {
            $attributes['end_date'] = $this->end_date->format('Y-m-d');
        }

        return $attributes;
    }
}
