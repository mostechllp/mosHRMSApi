<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceBreak extends Model
{
    protected $fillable = [
        'attendance_log_id',
        'start_time',
        'end_time',
        'duration_minutes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function attendanceLog()
    {
        return $this->belongsTo(AttendanceLog::class);
    }
}
