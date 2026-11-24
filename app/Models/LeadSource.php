<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadSource extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lead_sources';

    /**
     * Default sources seeded when table is first populated.
     */
    public const DEFAULTS = [
        'Website',
        'Referral',
        'Facebook',
        'Instagram',
        'LinkedIn',
        'WhatsApp',
        'Social Media',
        'Phone',
        'Email',
        'Exhibition',
        'Manual Entry',
        'Other',
    ];

    protected $fillable = [
        'name',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
