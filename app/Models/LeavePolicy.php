<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_type_id',
        'annual_allocation',
        'enable_accrual',
        'accrual_type',
        'accrual_days',
        'apply_during_probation',
        'probation_action',
        'release_after_probation',
        'enable_carry_forward',
        'unlimited_carry_forward',
        'maximum_carry_forward',
        'status',
    ];

    protected $casts = [
        'annual_allocation' => 'decimal:2',
        'accrual_days' => 'decimal:2',
        'maximum_carry_forward' => 'decimal:2',

        'enable_accrual' => 'boolean',
        'apply_during_probation' => 'boolean',
        'release_after_probation' => 'boolean',
        'enable_carry_forward' => 'boolean',
        'unlimited_carry_forward' => 'boolean',
        'status' => 'boolean',
    ];

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function accruals()
    {
        return $this->hasMany(LeaveAccrual::class);
    }
}