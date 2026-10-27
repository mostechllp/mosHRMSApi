<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCurrency extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_currencies';

    public const DEFAULTS = [
        ['code' => 'INR', 'symbol' => '₹', 'name' => 'Indian Rupee',    'is_default' => true],
        ['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar',       'is_default' => false],
        ['code' => 'AED', 'symbol' => 'AED', 'name' => 'UAE Dirham',     'is_default' => false],
        ['code' => 'GBP', 'symbol' => '£', 'name' => 'British Pound',   'is_default' => false],
    ];

    protected $fillable = [
        'code',
        'symbol',
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
