<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationCurrency extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'quotation_currencies';

    public const DEFAULTS = [
        'AED',
        'USD',
        'INR',
        'EUR',
        'GBP',
        'SGD',
        'CAD',
        'AUD',
    ];

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
