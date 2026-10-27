<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuotationPaymentTerm extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'quotation_payment_terms';

    public const DEFAULTS = [
        'Net 15',
        'Net 30',
        'Net 35',
        'Net 45',
        'Net 60',
        'Due on Receipt',
        'Advance',
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
