<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
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
        $leadId = $this->route('lead') ? $this->route('lead')->id : null;

        return [
            // Basic Information
            'lead_id'                 => ['nullable', 'string', 'max:50', Rule::unique('leads', 'lead_id')->ignore($leadId)],
            'lead_type'               => 'nullable|string|max:50',
            'lead_name'               => 'sometimes|required|string|max:255',
            'company_name'            => 'nullable|string|max:255',
            'designation'             => 'nullable|string|max:255',
            'industry'                => 'nullable|string|max:255',
            'website'                 => 'nullable|string|max:255',
            'lead_source'             => 'nullable|string|max:100',
            'lead_status'             => 'nullable|string|max:100',
            'priority'                => 'nullable|string|max:50',

            // Contact Details
            'email'                   => 'nullable|email|max:255',
            'primary_phone'           => 'nullable|string|max:50',
            'alternate_phone'         => 'nullable|string|max:50',
            'postal_code'             => 'nullable|string|max:20',
            'address'                 => 'nullable|string',
            'city'                    => 'nullable|string|max:100',
            'state'                   => 'nullable|string|max:100',
            'country'                 => 'nullable|string|max:100',

            // Sales Assignment
            'assigned_salesperson_id' => 'sometimes|required|exists:employees,id',
            'sales_team'              => 'nullable|string|max:100',
            'expected_value'          => 'nullable|numeric|min:0',
            'expected_closing_date'   => 'nullable|date',
            'interested_products'     => 'nullable|array',
            'interested_products.*'   => 'string',
            'notes'                   => 'nullable|string',

            // Follow-up
            'next_follow_up_at'       => 'nullable|date',
            'follow_up_type'          => 'nullable|string|max:50',
            'follow_up_notes'         => 'nullable|string',
        ];
    }
}
