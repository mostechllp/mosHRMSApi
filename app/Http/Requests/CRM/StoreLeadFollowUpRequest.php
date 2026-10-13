<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadFollowUpRequest extends FormRequest
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
            'follow_up_type' => 'required|string|max:50',
            'follow_up_date' => 'required|date',
            'status'         => 'nullable|string|in:scheduled,completed,cancelled,missed',
            'notes'          => 'nullable|string',
            'outcome'        => 'nullable|string',
            'performed_by'   => 'nullable|exists:employees,id',
        ];
    }
}
