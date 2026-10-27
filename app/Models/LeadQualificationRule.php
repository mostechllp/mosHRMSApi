<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadQualificationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lead_qualification_rules';

    /**
     * Default rules seeded when table is first populated.
     */
    public const DEFAULTS = [
        ['rule' => 'Budget confirmed', 'is_active' => true, 'sort_order' => 1],
        ['rule' => 'Decision maker identified', 'is_active' => true, 'sort_order' => 2],
        ['rule' => 'Requirement documented', 'is_active' => true, 'sort_order' => 3],
        ['rule' => 'Timeline within 6 months', 'is_active' => false, 'sort_order' => 4],
        ['rule' => 'Authority / signing power confirmed', 'is_active' => false, 'sort_order' => 5],
    ];

    protected $fillable = [
        'rule',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
