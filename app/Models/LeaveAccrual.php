<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveAccrual extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'leave_policy_id',
        'period',
        'accrual_date',
        'accrual_days',
        'status',
        'remarks',
    ];

    protected $casts = [
        'accrual_date' => 'date',
        'accrual_days' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leavePolicy()
    {
        return $this->belongsTo(LeavePolicy::class);
    }
}