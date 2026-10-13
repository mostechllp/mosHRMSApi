<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'contact_person',
        'contact_email',
        'contact_phone',
        'billing_address',
        'lead_id',
        'quotation_date',
        'valid_until',
        'currency',
        'payment_terms',
        'delivery_timeline',
        'subtotal',
        'discount_total',
        'tax_total',
        'additional_charges',
        'grand_total',
        'notes',
        'terms_and_conditions',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'quotation_date'     => 'date',
        'valid_until'        => 'date',
        'subtotal'           => 'decimal:2',
        'discount_total'     => 'decimal:2',
        'tax_total'          => 'decimal:2',
        'additional_charges' => 'decimal:2',
        'grand_total'        => 'decimal:2',
    ];

    protected $appends = [
        'customer_name',
        'company_name',
        'quote_number',
        'quotation_no',
        'number',
        'date',
        'quote_date',
        'formatted_date',
        'formatted_valid_until',
    ];

    public function getCustomerNameAttribute(): string
    {
        return $this->customer?->company_name
            ?? $this->customer?->contact_person_name
            ?? $this->contact_person
            ?? 'Customer';
    }

    public function getCompanyNameAttribute(): string
    {
        return $this->customer?->company_name ?? '';
    }

    public function getQuoteNumberAttribute(): string
    {
        return $this->quotation_number ?? ('Q-' . date('Y') . '-' . str_pad($this->id ?? 1, 4, '0', STR_PAD_LEFT));
    }

    public function getQuotationNoAttribute(): string
    {
        return $this->getQuoteNumberAttribute();
    }

    public function getNumberAttribute(): string
    {
        return $this->getQuoteNumberAttribute();
    }

    public function getDateAttribute(): ?string
    {
        return $this->quotation_date ? \Carbon\Carbon::parse($this->quotation_date)->format('Y-m-d') : null;
    }

    public function getQuoteDateAttribute(): ?string
    {
        return $this->getDateAttribute();
    }

    public function getFormattedDateAttribute(): ?string
    {
        return $this->quotation_date ? \Carbon\Carbon::parse($this->quotation_date)->format('d/m/Y') : null;
    }

    public function getFormattedValidUntilAttribute(): ?string
    {
        return $this->valid_until ? \Carbon\Carbon::parse($this->valid_until)->format('d/m/Y') : null;
    }

    protected static function booted(): void
    {
        static::creating(function ($quotation) {
            if (empty($quotation->quotation_number)) {
                $year   = now()->year;
                $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
                $quotation->quotation_number = 'Q-' . $year . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
