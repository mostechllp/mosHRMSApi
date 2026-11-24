<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class StoreOpportunityRequest extends FormRequest
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
            // Basic Information
            'opportunity_id'          => 'nullable|string|max:50|unique:opportunities,opportunity_id',
            'name'                    => 'required|string|max:255',
            'customer_id'             => 'required|exists:customers,id',
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
            'assigned_salesperson_id' => 'required|exists:employees,id',
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
            'attachments.*'           => 'file|max:20480', // Up to 20MB per file

            // Action triggers (from UI buttons)
            'action'                  => 'nullable|string|in:save,save_and_create_quotation,save_and_schedule_activity',

            // Optional activity payload for 'save_and_schedule_activity'
            'activity'                => 'nullable|array',
            'activity.title'          => 'nullable|string|max:255',
            'activity.activity_type'  => 'nullable|string|max:50',
            'activity.priority'       => 'nullable|string|max:50',
            'activity.start_date_time'=> 'nullable|date',
            'activity.end_date_time'  => 'nullable|date|after_or_equal:activity.start_date_time',
            'activity.description'    => 'nullable|string',
        ];
    }
}
