<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivityPriority extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'activity_priorities';

    public const DEFAULTS = [
        ['name' => 'Low',    'color' => '#10B981'],
        ['name' => 'Medium', 'color' => '#F59E0B'],
        ['name' => 'High',   'color' => '#EF4444'],
    ];

    protected $fillable = [
        'name',
        'color',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
