<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountsItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accounts_items';

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'usage_unit',
        'selling_price',
        'selling_currency',
        'sales_account',
        'sales_description',
        'cost_price',
        'cost_currency',
        'purchase_account',
        'preferred_vendor_id',
        'preferred_vendor_name',
        'purchase_description',
        'status',
        'source',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function preferredVendor(): BelongsTo
    {
        return $this->belongsTo(AccountsVendor::class, 'preferred_vendor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
