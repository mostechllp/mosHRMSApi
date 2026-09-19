<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffboardingHandover extends Model
{
    use HasFactory;

    protected $table = 'offboarding_handovers';

    protected $fillable = [
        'offboarding_id',
        'task_and_projects',
        'files_and_contents',
        'reporting_manager_confirmation',
        'notes',
    ];

    protected $casts = [
        'task_and_projects' => 'boolean',
        'files_and_contents' => 'boolean',
        'reporting_manager_confirmation' => 'boolean',
    ];

    public function offboarding(): BelongsTo
    {
        return $this->belongsTo(Offboarding::class);
    }
}