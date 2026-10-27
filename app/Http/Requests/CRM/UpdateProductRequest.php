<?php

namespace App\Http\Requests\CRM;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation.
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
        $productId = $this->route('product');
        if (is_object($productId)) {
            $productId = $productId->id;
        }

        return [
            'name'          => ['sometimes', 'required', 'string', 'max:255'],
            'code'          => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'code')->ignore($productId)],
            'type'          => ['sometimes', 'required', 'string', Rule::in(Product::TYPES)],
            'category_id'   => ['nullable', 'integer'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'unit'          => ['sometimes', 'required', 'string', 'max:50'],
            'default_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency'      => ['sometimes', 'required', 'string', 'max:10'],
            'tax_rate'      => ['sometimes', 'required', 'numeric', 'min:0', 'max:100'],
            'status'        => ['sometimes', 'required', 'string', Rule::in(Product::STATUSES)],
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
