<?php

namespace App\Http\Requests\CRM;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation (support alias fields like sku, price, etc.).
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        // Support 'sku' alias for 'code'
        if ($this->has('sku') && !$this->has('code')) {
            $merge['code'] = $this->input('sku');
        }

        // Support 'price' alias for 'default_price'
        if ($this->has('price') && !$this->has('default_price')) {
            $merge['default_price'] = $this->input('price');
        }

        // Support 'tax_rate_percent' or 'tax' alias for 'tax_rate'
        if ($this->has('tax') && !$this->has('tax_rate')) {
            $merge['tax_rate'] = $this->input('tax');
        }
        if ($this->has('tax_rate_percent') && !$this->has('tax_rate')) {
            $merge['tax_rate'] = $this->input('tax_rate_percent');
        }

        // Default type if omitted
        if (!$this->has('type')) {
            $merge['type'] = Product::TYPE_PRODUCT;
        }

        // Default status if omitted
        if (!$this->has('status')) {
            $merge['status'] = Product::STATUS_ACTIVE;
        }

        // Default currency if omitted
        if (!$this->has('currency')) {
            $merge['currency'] = 'INR';
        }

        // Default unit if omitted
        if (!$this->has('unit')) {
            $merge['unit'] = 'Unit';
        }

        // Default tax_rate if omitted
        if (!$this->has('tax_rate') && !isset($merge['tax_rate'])) {
            $merge['tax_rate'] = 18.00;
        }

        // Default default_price if omitted
        if (!$this->has('default_price') && !isset($merge['default_price'])) {
            $merge['default_price'] = 0.00;
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'code'          => ['required', 'string', 'max:100', 'unique:products,code'],
            'type'          => ['required', 'string', Rule::in(Product::TYPES)],
            'category_id'   => ['nullable', 'integer'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'unit'          => ['required', 'string', 'max:50'],
            'default_price' => ['required', 'numeric', 'min:0'],
            'currency'      => ['required', 'string', 'max:10'],
            'tax_rate'      => ['required', 'numeric', 'min:0', 'max:100'],
            'status'        => ['required', 'string', Rule::in(Product::STATUSES)],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name'          => 'Product/Service name',
            'code'          => 'Code / SKU',
            'type'          => 'Item type',
            'category_id'   => 'Category',
            'description'   => 'Description',
            'unit'          => 'Unit',
            'default_price' => 'Default price',
            'currency'      => 'Currency',
            'tax_rate'      => 'Tax rate (%)',
            'status'        => 'Status',
            'notes'         => 'Notes',
        ];
    }
}
