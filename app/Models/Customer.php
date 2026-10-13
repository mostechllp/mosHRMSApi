<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'customer_type',
        'company_name',
        'industry',
        'company_registration_no',
        'tax_gst_no',
        'website',
        'customer_status',
        'customer_since',
        'account_manager_id',
        'billing_address',
        'shipping_address',
        'city',
        'state',
        'country',
        'postal_code',
        'contact_person_name',
        'contact_designation',
        'contact_email',
        'contact_phone',
        'contact_alternate_phone',
        'preferred_communication',
        'customer_category',
        'customer_source',
        'payment_terms',
        'credit_limit',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'customer_since' => 'date',
        'credit_limit'   => 'decimal:2',
    ];

    protected $appends = [
        'name',
        'email',
        'phone',
    ];

    public function getNameAttribute(): string
    {
        return $this->company_name ?? '';
    }

    public function getEmailAttribute(): ?string
    {
        return $this->contact_email;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->contact_phone;
    }

    /**
     * Auto-generate customer_id on creating if not provided.
     */
    protected static function booted(): void
    {
        static::creating(function ($customer) {
            if (empty($customer->customer_id)) {
                $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
                $customer->customer_id = 'CUS-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function accountManager()
    {
        return $this->belongsTo(Employee::class, 'account_manager_id');
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
