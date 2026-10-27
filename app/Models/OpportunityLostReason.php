<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityLostReason extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'opportunity_lost_reasons';

    public const DEFAULTS = [
        'Price too high',
        'Lost to competitor',
        'No budget',
        'Timing not right',
        'No decision',
        'Product fit',
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
