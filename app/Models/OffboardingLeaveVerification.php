<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffboardingLeaveVerification extends Model
{
    use HasFactory;

    protected $table = 'offboarding_leave_verifications';

    protected $fillable = [
        'offboarding_id',
        'leave_history_verified',
        'process_for_encashment',
        'remarks',
    ];

    protected $casts = [
        'leave_history_verified' => 'boolean',
        'process_for_encashment' => 'boolean',
    ];

    public function offboarding(): BelongsTo
    {
        return $this->belongsTo(Offboarding::class);
    }
}
