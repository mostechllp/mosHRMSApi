<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'opportunity_id',
        'name',
        'customer_id',
        'contact_person',
        'lead_source',
        'products',
        'product',
        'description',
        'estimated_value',
        'currency',
        'stage',
        'probability',
        'expected_closing_date',
        'assigned_salesperson_id',
        'sales_team',
        'priority',
        'competitor',
        'decision_maker',
        'customer_requirements',
        'next_action',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'products'              => 'array',
        'estimated_value'       => 'decimal:2',
        'probability'           => 'decimal:2',
        'expected_closing_date' => 'date',
    ];

    protected $appends = [
        'customer_name',
        'assigned_salesperson_name',
        'formatted_value',
        'formatted_expected_closing_date',
        'product_display',
        'stage_badge',
        'priority_badge',
    ];

    /**
     * Auto-generate opportunity_id on creating if not provided.
     */
    protected static function booted(): void
    {
        static::creating(function ($opportunity) {
            if (empty($opportunity->opportunity_id)) {
                $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
                $opportunity->opportunity_id = 'OPP-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }

            // Sync single product with products array if needed
            if (empty($opportunity->product) && !empty($opportunity->products) && is_array($opportunity->products)) {
                $opportunity->product = $opportunity->products[0] ?? null;
            } elseif (!empty($opportunity->product) && empty($opportunity->products)) {
                $opportunity->products = [$opportunity->product];
            }
        });

        static::updating(function ($opportunity) {
            // Keep single product and products array aligned
            if (empty($opportunity->product) && !empty($opportunity->products) && is_array($opportunity->products)) {
                $opportunity->product = $opportunity->products[0] ?? null;
            }
        });
    }

    // ─── Accessors ─────────────────────────────────────────────────────────────

    public function getCustomerNameAttribute(): string
    {
        return $this->customer?->company_name
            ?? $this->customer?->contact_person_name
            ?? 'N/A';
    }

    public function getAssignedSalespersonNameAttribute(): string
    {
        if (!$this->assignedSalesperson) {
            return 'Unassigned';
        }

        $first = $this->assignedSalesperson->first_name ?? '';
        $last  = $this->assignedSalesperson->last_name ?? '';
        return trim($first . ' ' . $last) ?: 'Salesperson #' . $this->assigned_salesperson_id;
    }

    /**
     * Format estimated value nicely (e.g. ₹2.00L, ₹80K, ₹19.50L).
     */
    public function getFormattedValueAttribute(): string
    {
        $val = (float) ($this->estimated_value ?? 0);
        $curr = strtoupper($this->currency ?? 'INR');
        $symbol = ($curr === 'INR') ? '₹' : ($curr === 'USD' ? '$' : ($curr === 'EUR' ? '€' : ($curr === 'GBP' ? '£' : $curr . ' ')));

        if ($curr === 'INR') {
            if ($val >= 10000000) {
                return $symbol . number_format($val / 10000000, 2) . 'Cr';
            }
            if ($val >= 100000) {
                return $symbol . number_format($val / 100000, 2) . 'L';
            }
            if ($val >= 1000) {
                return $symbol . round($val / 1000) . 'K';
            }
            return $symbol . number_format($val, 2);
        }

        // Global formatting for other currencies (e.g. $10K, $1.5M)
        if ($val >= 1000000) {
            return $symbol . number_format($val / 1000000, 2) . 'M';
        }
        if ($val >= 1000) {
            return $symbol . round($val / 1000) . 'K';
        }
        return $symbol . number_format($val, 2);
    }

    public function getFormattedExpectedClosingDateAttribute(): ?string
    {
        if (!$this->expected_closing_date) {
            return null;
        }

        return Carbon::parse($this->expected_closing_date)->format('d M Y');
    }

    public function getProductDisplayAttribute(): string
    {
        if (!empty($this->product)) {
            return $this->product;
        }

        if (is_array($this->products) && count($this->products) > 0) {
            return implode(', ', $this->products);
        }

        return '-';
    }

    public function getStageBadgeAttribute(): array
    {
        $stage = $this->stage ?? 'New';
        return match (strtolower(trim($stage))) {
            'new' => [
                'name'  => 'New',
                'bg'    => '#EFF6FF',
                'color' => '#1D4ED8',
                'badge' => 'primary',
            ],
            'qualified' => [
                'name'  => 'Qualified',
                'bg'    => '#F3E8FF',
                'color' => '#7E22CE',
                'badge' => 'purple',
            ],
            'proposal sent' => [
                'name'  => 'Proposal Sent',
                'bg'    => '#EEF2FF',
                'color' => '#4338CA',
                'badge' => 'indigo',
            ],
            'negotiation' => [
                'name'  => 'Negotiation',
                'bg'    => '#FEF3C7',
                'color' => '#B45309',
                'badge' => 'warning',
            ],
            'won' => [
                'name'  => 'Won',
                'bg'    => '#ECFDF5',
                'color' => '#047857',
                'badge' => 'success',
            ],
            'lost' => [
                'name'  => 'Lost',
                'bg'    => '#FEE2E2',
                'color' => '#B91C1C',
                'badge' => 'danger',
            ],
            default => [
                'name'  => $stage,
                'bg'    => '#F3F4F6',
                'color' => '#374151',
                'badge' => 'secondary',
            ],
        };
    }

    public function getPriorityBadgeAttribute(): array
    {
        $priority = $this->priority ?? 'Medium';
        return match (strtolower(trim($priority))) {
            'high' => [
                'name'  => 'High',
                'bg'    => '#FEE2E2',
                'color' => '#DC2626',
            ],
            'medium' => [
                'name'  => 'Medium',
                'bg'    => '#FEF3C7',
                'color' => '#D97706',
            ],
            'low' => [
                'name'  => 'Low',
                'bg'    => '#ECFDF5',
                'color' => '#059669',
            ],
            default => [
                'name'  => $priority,
                'bg'    => '#F3F4F6',
                'color' => '#4B5563',
            ],
        };
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Alias for customer relation to support both naming conventions.
     */
    public function customerRelation()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function assignedSalesperson()
    {
        return $this->belongsTo(Employee::class, 'assigned_salesperson_id');
    }

    public function attachments()
    {
        return $this->hasMany(OpportunityAttachment::class)->orderBy('created_at', 'desc');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class)->orderBy('created_at', 'desc');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'related_record_id')
            ->where('related_record_type', 'Opportunity')
            ->orderBy('start_date_time', 'desc');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->whereNotIn('stage', ['Won', 'Lost']);
    }

    public function scopeWon($query)
    {
        return $query->where('stage', 'Won');
    }

    public function scopeLost($query)
    {
        return $query->where('stage', 'Lost');
    }
}
