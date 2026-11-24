<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTaxRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_tax_rates';

    public const DEFAULTS = [
        ['name' => 'GST 18%', 'rate' => 18.00, 'is_default' => true],
        ['name' => 'GST 12%', 'rate' => 12.00, 'is_default' => false],
        ['name' => 'GST 5%',  'rate' => 5.00,  'is_default' => false],
        ['name' => 'Exempt',  'rate' => 0.00,  'is_default' => false],
    ];

    protected $fillable = [
        'name',
        'rate',
        'is_default',
    ];

    protected $casts = [
        'rate'       => 'decimal:2',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
