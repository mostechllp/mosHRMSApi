<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerIndustry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customer_industries';

    public const DEFAULTS = [
        'IT Services',
        'Manufacturing',
        'Fintech',
        'Retail',
        'Logistics',
        'Healthcare',
        'Aerospace',
        'Education',
        'Real Estate',
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
