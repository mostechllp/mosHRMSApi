<?php

namespace App\Http\Controllers\Api\Admin\Accounts;

use App\Http\Controllers\Api\ApiController;
use App\Models\AccountsCustomer;
use App\Models\AccountsCustomerContactPerson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactPersonApiController extends ApiController
{
    /**
     * List all contact persons for a specific customer.
     */
    public function index($customerId): JsonResponse
    {
        $customer = AccountsCustomer::find($customerId);
        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $contactPersons = AccountsCustomerContactPerson::where('customer_id', $customerId)
            ->latest()
            ->get();

        return $this->success($contactPersons);
    }

    /**
     * Store a newly created contact person for a customer.
     * Fields: salutation, firstname, lastname, email, work phone, mobile
     */
    public function store(Request $request, $customerId): JsonResponse
    {
        $customer = AccountsCustomer::find($customerId);
        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $request->validate([
            'salutation'  => 'nullable|string|max:20',
            'first_name'  => 'nullable|string|max:100',
            'firstName'   => 'nullable|string|max:100',
            'last_name'   => 'nullable|string|max:100',
            'lastName'    => 'nullable|string|max:100',
            'email'       => 'nullable|email|max:255',
            'work_phone'  => 'nullable|string|max:50',
            'workPhone'   => 'nullable|string|max:50',
            'mobile'      => 'nullable|string|max:50',
        ]);

        $firstName = $request->input('firstName') ?? $request->input('first_name');
        $lastName  = $request->input('lastName') ?? $request->input('last_name');
        $workPhone = $request->input('workPhone') ?? $request->input('work_phone');

        $contactPerson = AccountsCustomerContactPerson::create([
            'customer_id' => $customer->id,
            'salutation'  => $request->input('salutation'),
            'first_name'  => $firstName,
            'last_name'   => $lastName,
            'email'       => $request->input('email'),
            'work_phone'  => $workPhone,
            'mobile'      => $request->input('mobile'),
        ]);

        // Keep JSON column on customer updated in sync
        $this->syncCustomerJson($customer);

        return $this->success($contactPerson, 'Contact person created successfully', 201);
    }

    /**
     * Display a specific contact person.
     */
    public function show($customerId, $id): JsonResponse
    {
        $contactPerson = AccountsCustomerContactPerson::where('customer_id', $customerId)->find($id);

        if (!$contactPerson) {
            return $this->error('Contact person not found', 404);
        }

        return $this->success($contactPerson);
    }

    /**
     * Update an existing contact person.
     */
    public function update(Request $request, $customerId, $id): JsonResponse
    {
        $customer = AccountsCustomer::find($customerId);
        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $contactPerson = AccountsCustomerContactPerson::where('customer_id', $customerId)->find($id);
        if (!$contactPerson) {
            return $this->error('Contact person not found', 404);
        }

        $request->validate([
            'salutation'  => 'nullable|string|max:20',
            'first_name'  => 'nullable|string|max:100',
            'firstName'   => 'nullable|string|max:100',
            'last_name'   => 'nullable|string|max:100',
            'lastName'    => 'nullable|string|max:100',
            'email'       => 'nullable|email|max:255',
            'work_phone'  => 'nullable|string|max:50',
            'workPhone'   => 'nullable|string|max:50',
            'mobile'      => 'nullable|string|max:50',
        ]);

        $firstName = $request->input('firstName') ?? $request->input('first_name', $contactPerson->first_name);
        $lastName  = $request->input('lastName') ?? $request->input('last_name', $contactPerson->last_name);
        $workPhone = $request->input('workPhone') ?? $request->input('work_phone', $contactPerson->work_phone);

        $contactPerson->update([
            'salutation'  => $request->input('salutation', $contactPerson->salutation),
            'first_name'  => $firstName,
            'last_name'   => $lastName,
            'email'       => $request->input('email', $contactPerson->email),
            'work_phone'  => $workPhone,
            'mobile'      => $request->input('mobile', $contactPerson->mobile),
        ]);

        $this->syncCustomerJson($customer);

        return $this->success($contactPerson->fresh(), 'Contact person updated successfully');
    }

    /**
     * Delete a contact person.
     */
    public function destroy($customerId, $id): JsonResponse
    {
        $customer = AccountsCustomer::find($customerId);
        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $contactPerson = AccountsCustomerContactPerson::where('customer_id', $customerId)->find($id);
        if (!$contactPerson) {
            return $this->error('Contact person not found', 404);
        }

        $contactPerson->delete();
        $this->syncCustomerJson($customer);

        return $this->success(null, 'Contact person deleted successfully');
    }

    /**
     * Keep customer JSON contact_persons column synchronized.
     */
    private function syncCustomerJson(AccountsCustomer $customer): void
    {
        $all = AccountsCustomerContactPerson::where('customer_id', $customer->id)
            ->get()
            ->map(function ($cp) {
                return [
                    'id'         => $cp->id,
                    'salutation' => $cp->salutation,
                    'firstName'  => $cp->first_name,
                    'lastName'   => $cp->last_name,
                    'email'      => $cp->email,
                    'workPhone'  => $cp->work_phone,
                    'mobile'     => $cp->mobile,
                ];
            })
            ->toArray();

        $customer->update(['contact_persons' => $all]);
    }
}
