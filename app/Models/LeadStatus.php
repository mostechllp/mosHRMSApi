<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lead_statuses';

    /**
     * Default statuses seeded when table is first populated.
     */
    public const DEFAULTS = [
        ['name' => 'New',          'color' => '#3B82F6', 'sort_order' => 1, 'is_default' => true],
        ['name' => 'Contacted',    'color' => '#F59E0B', 'sort_order' => 2, 'is_default' => false],
        ['name' => 'Qualified',    'color' => '#10B981', 'sort_order' => 3, 'is_default' => false],
        ['name' => 'Unqualified',  'color' => '#6B7280', 'sort_order' => 4, 'is_default' => false],
        ['name' => 'Converted',    'color' => '#8B5CF6', 'sort_order' => 5, 'is_default' => false],
    ];

    protected $fillable = [
        'name',
        'color',
        'sort_order',
        'is_default',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default'  => 'boolean',
        'sort_order'  => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
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
