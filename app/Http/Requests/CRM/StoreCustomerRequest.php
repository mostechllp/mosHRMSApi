<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // A. Company Details
            'customer_id'             => 'nullable|string|max:50|unique:customers,customer_id',
            'customer_type'           => 'nullable|string|max:50',
            'company_name'            => 'required|string|max:255',
            'industry'                => 'nullable|string|max:255',
            'company_registration_no' => 'nullable|string|max:100',
            'tax_gst_no'              => 'nullable|string|max:100',
            'website'                 => 'nullable|string|max:255',
            'customer_status'         => 'nullable|string|max:50',
            'customer_since'          => 'nullable|date',
            'account_manager_id'      => 'required|exists:employees,id',

            // B. Address
            'billing_address'         => 'nullable|string',
            'shipping_address'        => 'nullable|string',
            'city'                    => 'nullable|string|max:100',
            'state'                   => 'nullable|string|max:100',
            'country'                 => 'nullable|string|max:100',
            'postal_code'             => 'nullable|string|max:20',

            // C. Primary Contact
            'contact_person_name'     => 'required|string|max:255',
            'contact_designation'     => 'nullable|string|max:255',
            'contact_email'           => 'nullable|email|max:255',
            'contact_phone'           => 'nullable|string|max:50',
            'contact_alternate_phone' => 'nullable|string|max:50',
            'preferred_communication' => 'nullable|string|max:50',

            // D. Business Details
            'customer_category'       => 'nullable|string|max:100',
            'customer_source'         => 'nullable|string|max:100',
            'payment_terms'           => 'nullable|string|max:100',
            'credit_limit'            => 'nullable|numeric|min:0',
            'notes'                   => 'nullable|string',
        ];
    }
}
