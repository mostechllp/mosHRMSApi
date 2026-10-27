<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    public const TYPE_PRODUCT = 'Product';
    public const TYPE_SERVICE = 'Service';

    public const TYPES = [
        self::TYPE_PRODUCT,
        self::TYPE_SERVICE,
    ];

    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    public const DEFAULT_UNITS = [
        'Unit',
        'Hour',
        'Day',
        'Month',
        'Year',
        'Piece',
        'Box',
        'Kg',
        'Meter',
        'License',
        'User',
    ];

    public const DEFAULT_CURRENCIES = [
        'INR',
        'USD',
        'EUR',
        'GBP',
        'AED',
    ];

    public const DEFAULT_CATEGORIES = [
        'Human Resources',
        'Payroll',
        'Recruitment',
        'Consulting',
        'Software License',
        'Hardware',
        'Maintenance & Support',
        'Training',
        'Custom Development',
        'Other',
    ];

    protected $fillable = [
        'name',
        'code',
        'type',
        'category_id',
        'description',
        'unit',
        'default_price',
        'currency',
        'tax_rate',
        'status',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'default_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'sku',
        'price',
        'category',
    ];

    /**
     * Category name accessor via relation (safe fallback).
     */
    public function getCategoryAttribute(): ?string
    {
        try {
            return $this->categoryRelation?->name;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Alias for code (SKU).
     */
    public function getSkuAttribute(): ?string
    {
        return $this->code;
    }

    /**
     * Alias for default_price.
     */
    public function getPriceAttribute(): ?string
    {
        return $this->default_price;
    }

    /**
     * Creator relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Category relationship.
     */
    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Updater relationship.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope active items.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope inactive items.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }
}
