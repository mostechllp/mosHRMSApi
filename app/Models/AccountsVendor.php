<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountsVendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accounts_vendors';

    protected $fillable = [
        'company_id',
        'name',
        'company_name',
        'email',
        'phone',
        'mobile',
        'gstin_pan',
        'currency',
        'opening_balance',
        'payment_terms',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccountsItem::class, 'preferred_vendor_id');
    }
}
