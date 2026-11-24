<?php

namespace App\Http\Requests\CRM;

use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare data for validation (handle camelCase / UI field names).
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('s_no') && !$this->has('serial_no')) {
            $merge['serial_no'] = $this->input('s_no');
        }

        if ($this->has('proposal_reference') && !$this->has('proposal_reference_no')) {
            $merge['proposal_reference_no'] = $this->input('proposal_reference');
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
        $isPut = $this->isMethod('put');

        return [
            'serial_no'             => [$isPut ? 'required' : 'sometimes', 'integer', 'min:1'],
            'date'                  => [$isPut ? 'required' : 'sometimes', 'date'],
            'customer'              => [$isPut ? 'required' : 'sometimes', 'string', 'max:255'],
            'customer_id'           => ['nullable', 'exists:customers,id'],
            'proposal_reference_no' => [$isPut ? 'required' : 'sometimes', 'string', 'max:100'],
            'drive_link'            => [$isPut ? 'required' : 'sometimes', 'string', 'max:2000'],
            'status'                => [$isPut ? 'required' : 'sometimes', 'string', Rule::in(Proposal::STATUSES)],
        ];
    }

    /**
     * Custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'serial_no'             => 'S.No',
            'date'                  => 'Date',
            'customer'              => 'Customer',
            'proposal_reference_no' => 'Proposal Reference #',
            'drive_link'            => 'Drive Link',
            'status'                => 'Status',
        ];
    }
}
