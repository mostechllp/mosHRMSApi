<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountsCustomer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accounts_customers';

    protected $fillable = [
        'company_id',
        'customer_type',
        'salutation',
        'first_name',
        'last_name',
        'company_name',
        'display_name',
        'email',
        'country',
        'work_phone',
        'mobile',
        'gstin_pan',
        'pan',
        'currencies',
        'base_currency',
        'exchange_rates',
        'receivable_account',
        'opening_balance',
        'payment_terms',
        'portal_access',
        'billing_attention',
        'billing_country',
        'billing_street1',
        'billing_street2',
        'billing_city',
        'billing_state',
        'billing_pin_code',
        'billing_phone',
        'billing_fax',
        'shipping_attention',
        'shipping_country',
        'shipping_street1',
        'shipping_street2',
        'shipping_city',
        'shipping_state',
        'shipping_pin_code',
        'shipping_phone',
        'shipping_fax',
        'contact_persons',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'portal_access' => 'boolean',
        'currencies' => 'array',
        'exchange_rates' => 'array',
        'contact_persons' => 'array',
    ];

    // protected $appends = [
    //     'billing_address',
    //     'shipping_address',
    //     'displayName',
    //     'firstName',
    //     'lastName',
    //     'companyName',
    //     'workPhone',
    //     'gstinPan',
    //     'baseCurrency',
    //     'exchangeRates',
    //     'receivableAccount',
    //     'openingBalance',
    //     'paymentTerms',
    //     'portalAccess',
    // ];

    // Nested Address Accessors
    public function getBillingAddressAttribute(): array
    {
        return [
            'attention' => $this->billing_attention ?? '',
            'country' => $this->billing_country ?? ($this->country ?? ''),
            'street1' => $this->billing_street1 ?? '',
            'street2' => $this->billing_street2 ?? '',
            'city' => $this->billing_city ?? '',
            'state' => $this->billing_state ?? '',
            'pinCode' => $this->billing_pin_code ?? '',
            'phone' => $this->billing_phone ?? '',
            'fax' => $this->billing_fax ?? '',
        ];
    }

    public function getShippingAddressAttribute(): array
    {
        return [
            'attention' => $this->shipping_attention ?? '',
            'country' => $this->shipping_country ?? ($this->country ?? ''),
            'street1' => $this->shipping_street1 ?? '',
            'street2' => $this->shipping_street2 ?? '',
            'city' => $this->shipping_city ?? '',
            'state' => $this->shipping_state ?? '',
            'pinCode' => $this->shipping_pin_code ?? '',
            'phone' => $this->shipping_phone ?? '',
            'fax' => $this->shipping_fax ?? '',
        ];
    }

    public function getBillingAddressAttributeCamel(): array
    {
        return $this->getBillingAddressAttribute();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->attributes['display_name'] ?? '';
    }

    public function getFirstNameAttribute(): ?string
    {
        return $this->attributes['first_name'] ?? null;
    }

    public function getLastNameAttribute(): ?string
    {
        return $this->attributes['last_name'] ?? null;
    }

    public function getCompanyNameAttribute(): ?string
    {
        return $this->attributes['company_name'] ?? null;
    }

    public function getWorkPhoneAttribute(): ?string
    {
        return $this->attributes['work_phone'] ?? null;
    }

    public function getGstinPanAttribute(): ?string
    {
        return $this->attributes['gstin_pan'] ?? ($this->attributes['pan'] ?? null);
    }

    public function getBaseCurrencyAttribute(): string
    {
        return $this->attributes['base_currency'] ?? 'INR';
    }

    public function getExchangeRatesAttribute(): array
    {
        $raw = $this->attributes['exchange_rates'] ?? null;
        if (is_string($raw)) {
            return json_decode($raw, true) ?: [];
        }
        return is_array($raw) ? $raw : [
            'USD' => 0.00,
            'EUR' => 0.00,
            'GBP' => 0.00,
            'AED' => 0.00,
            'SAR' => 0.00,
            'JPY' => 0.00,
            'RUB' => 0.00,
            'CNY' => 0.00,
        ];
    }

    public function getReceivableAccountAttribute(): ?string
    {
        return $this->attributes['receivable_account'] ?? 'Accounts Receivable';
    }

    public function getOpeningBalanceAttribute(): float
    {
        return (float) ($this->attributes['opening_balance'] ?? 0);
    }

    public function getPaymentTermsAttribute(): ?string
    {
        return $this->attributes['payment_terms'] ?? 'Due on receipt';
    }

    public function getPortalAccessAttribute(): bool
    {
        return (bool) ($this->attributes['portal_access'] ?? false);
    }

    // Relationships
    public function contactPersons()
    {
        return $this->hasMany(AccountsCustomerContactPerson::class, 'customer_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
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
