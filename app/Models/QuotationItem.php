<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id',
        'product_service',
        'description',
        'quantity',
        'unit_price',
        'discount',
        'discount_type',
        'tax_percent',
        'line_total',
        'sort_order',
    ];

    protected $casts = [
        'quantity'     => 'decimal:2',
        'unit_price'   => 'decimal:2',
        'discount'     => 'decimal:2',
        'tax_percent'  => 'decimal:2',
        'line_total'   => 'decimal:2',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Calculate line total based on qty, unit_price, discount & tax.
     */
    public static function calculateLineTotal(
        float $qty,
        float $unitPrice,
        float $discount,
        string $discountType,
        float $taxPercent
    ): float {
        $baseAmount = $qty * $unitPrice;

        if ($discountType === 'percent') {
            $discountAmount = $baseAmount * ($discount / 100);
        } else {
            $discountAmount = $discount;
        }

        $afterDiscount = $baseAmount - $discountAmount;
        $taxAmount     = $afterDiscount * ($taxPercent / 100);

        return round($afterDiscount + $taxAmount, 2);
    }
}
