<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OffboardingSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'offboarding_id',
        'total_payable',
        'total_deductions',
        'net_payable',
        'status',
        'remarks',
        'deductions',
    ];

    protected $casts = [
        'deductions' => 'array',
        'total_payable' => 'float',
        'total_deductions' => 'float',
        'net_payable' => 'float',
    ];

    public function offboarding()
    {
        return $this->belongsTo(Offboarding::class);
    }
}
