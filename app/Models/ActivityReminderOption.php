<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityReminderOption extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'activity_reminder_options';

    public const DEFAULTS = [
        ['name' => 'None',    'minutes' => 0],
        ['name' => '15 min',  'minutes' => 15],
        ['name' => '1 hour',  'minutes' => 60],
        ['name' => '1 day',   'minutes' => 1440],
    ];

    protected $fillable = [
        'name',
        'minutes',
    ];

    protected $casts = [
        'minutes'    => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
