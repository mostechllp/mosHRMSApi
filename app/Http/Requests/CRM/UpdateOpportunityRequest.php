<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityRequest extends FormRequest
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
        $opportunityId = $this->route('opportunity')?->id ?? $this->route('opportunity');

        return [
            // Basic Information
            'opportunity_id'          => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('opportunities', 'opportunity_id')->ignore($opportunityId),
            ],
            'name'                    => 'sometimes|required|string|max:255',
            'customer_id'             => 'sometimes|required|exists:customers,id',
            'contact_person'          => 'nullable|string|max:255',
            'lead_source'             => 'nullable|string|max:100',
            'products'                => 'nullable|array',
            'products.*'              => 'string|max:100',
            'product'                 => 'nullable|string|max:100',
            'description'             => 'nullable|string',

            // Sales Information
            'estimated_value'         => 'nullable|numeric|min:0',
            'currency'                => 'nullable|string|max:10',
            'stage'                   => 'nullable|string|max:50',
            'probability'             => 'nullable|numeric|min:0|max:100',
            'expected_closing_date'   => 'nullable|date',
            'assigned_salesperson_id' => 'sometimes|required|exists:employees,id',
            'sales_team'              => 'nullable|string|max:100',
            'priority'                => 'nullable|string|max:50',

            // Additional Information
            'competitor'              => 'nullable|string|max:255',
            'decision_maker'          => 'nullable|string|max:255',
            'customer_requirements'   => 'nullable|string',
            'next_action'             => 'nullable|string|max:255',
            'notes'                   => 'nullable|string',

            // Attachments
            'attachments'             => 'nullable|array',
            'attachments.*'           => 'file|max:20480',
        ];
    }
}
