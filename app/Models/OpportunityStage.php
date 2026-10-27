<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityStage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'opportunity_stages';

    /**
     * Default stages matching pipeline steps.
     */
    public const DEFAULTS = [
        ['name' => 'New',           'color' => '#3B82F6', 'default_probability' => 20,  'sort_order' => 1],
        ['name' => 'Qualified',     'color' => '#6366F1', 'default_probability' => 40,  'sort_order' => 2],
        ['name' => 'Proposal Sent', 'color' => '#8B5CF6', 'default_probability' => 60,  'sort_order' => 3],
        ['name' => 'Negotiation',   'color' => '#F59E0B', 'default_probability' => 75,  'sort_order' => 4],
        ['name' => 'Won',           'color' => '#10B981', 'default_probability' => 100, 'sort_order' => 5],
        ['name' => 'Lost',          'color' => '#EF4444', 'default_probability' => 0,   'sort_order' => 6],
    ];

    protected $fillable = [
        'name',
        'color',
        'default_probability',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'default_probability' => 'decimal:2',
        'sort_order'          => 'integer',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
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
