<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadPriority extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lead_priorities';

    /**
     * Default priorities seeded when table is first populated.
     */
    public const DEFAULTS = [
        ['name' => 'Low',    'color' => '#10B981', 'sort_order' => 1],
        ['name' => 'Medium', 'color' => '#F59E0B', 'sort_order' => 2],
        ['name' => 'High',   'color' => '#EF4444', 'sort_order' => 3],
    ];

    protected $fillable = [
        'name',
        'color',
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
