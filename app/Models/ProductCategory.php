<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_categories';

    public const STATUS_ACTIVE   = 'Active';
    public const STATUS_INACTIVE = 'Inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'products_count',
    ];

    protected static function booted(): void
    {
        static::saving(function ($category) {
            if (empty($category->slug) && !empty($category->name)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Associated products.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Products count accessor.
     */
    public function getProductsCountAttribute(): int
    {
        if (array_key_exists('products_count', $this->attributes)) {
            return (int) $this->attributes['products_count'];
        }

        if ($this->relationLoaded('products')) {
            return $this->products->count();
        }

        try {
            return Product::query()
                ->where('category_id', $this->id)
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Creator relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Updater relationship.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Active scope.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
