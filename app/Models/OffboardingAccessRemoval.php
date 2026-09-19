<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffboardingAccessRemoval extends Model
{
    use HasFactory;

    protected $table = 'offboarding_access_removals';

    protected $fillable = [
        'offboarding_id',
        'hrms_access_revoke',
        'deactivate_company_email',
        'other_access',
        'notes',
    ];

    protected $casts = [
        'hrms_access_revoke' => 'boolean',
        'deactivate_company_email' => 'boolean',
    ];

    public function offboarding(): BelongsTo
    {
        return $this->belongsTo(Offboarding::class);
    }
}
