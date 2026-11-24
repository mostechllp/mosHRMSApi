<?php

namespace App\Http\Controllers\Api\Admin\Accounts;

use App\Http\Controllers\Api\ApiController;
use App\Models\AccountsCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerApiController extends ApiController
{
    /**
     * Display a listing of accounts customers with search, filters & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AccountsCustomer::query()->latest();

        // Multi-tenant check
        $user = auth('api')->user();
        if ($user && $user->company_id) {
            $query->where(function ($q) use ($user) {
                $q->where('company_id', $user->company_id)
                  ->orWhereNull('company_id');
            });
        }

        // Search by name, company, email, phone
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%")
                  ->orWhere('work_phone', 'like', "%{$search}%");
            });
        }

        // Filter by Customer Type (Business / Individual)
        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        $perPage = max(1, (int) $request->input('per_page', 15));
        $customers = $query->paginate($perPage);

        return $this->success($customers);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => 'required_without:displayName|string|max:255',
            'displayName'  => 'nullable|string|max:255',
            'type'         => 'nullable|in:Business,Individual,business,individual',
            'customer_type'=> 'nullable|in:Business,Individual,business,individual',
            'email'        => 'nullable|email|max:255',
            'salutation'   => 'nullable|string|max:20',
            'first_name'   => 'nullable|string|max:100',
            'firstName'    => 'nullable|string|max:100',
            'last_name'    => 'nullable|string|max:100',
            'lastName'     => 'nullable|string|max:100',
            'company_name' => 'nullable|string|max:255',
            'companyName'  => 'nullable|string|max:255',
            'work_phone'   => 'nullable|string|max:50',
            'workPhone'    => 'nullable|string|max:50',
            'mobile'       => 'nullable|string|max:50',
            'pan'          => 'nullable|string|max:50',
            'currency'     => 'nullable|string|max:50',
            'opening_balance' => 'nullable|numeric',
            'openingBalance'  => 'nullable|numeric',
            'payment_terms'   => 'nullable|string|max:100',
            'paymentTerms'    => 'nullable|string|max:100',
            'portal_access'   => 'nullable|boolean',
            'portalAccess'    => 'nullable|boolean',
            'remarks'         => 'nullable|string',
            'billingAddress'  => 'nullable|array',
            'shippingAddress' => 'nullable|array',
            'billing_address' => 'nullable|array',
            'shipping_address'=> 'nullable|array',
            'contactPersons'  => 'nullable|array',
            'contact_persons' => 'nullable|array',
            'customFields'    => 'nullable|array',
            'custom_fields'   => 'nullable|array',
            'reportingTags'   => 'nullable|array',
            'reporting_tags'  => 'nullable|array',
        ]);

        $payload = $this->formatCustomerPayload($request);

        $user = auth('api')->user();
        if ($user) {
            $payload['created_by'] = $user->id;
            $payload['company_id'] = $user->company_id ?? null;
        }

        $customer = AccountsCustomer::create($payload);

        return $this->success($customer, 'Customer created successfully', 201);
    }

    /**
     * Display the specified customer.
     */
    public function show($id): JsonResponse
    {
        $customer = AccountsCustomer::with(['company', 'creator', 'updater'])->find($id);

        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        return $this->success($customer);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $customer = AccountsCustomer::find($id);

        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $request->validate([
            'display_name' => 'nullable|string|max:255',
            'displayName'  => 'nullable|string|max:255',
            'email'        => 'nullable|email|max:255',
            'type'         => 'nullable|in:Business,Individual,business,individual',
            'customer_type'=> 'nullable|in:Business,Individual,business,individual',
        ]);

        $payload = $this->formatCustomerPayload($request, $customer);

        $user = auth('api')->user();
        if ($user) {
            $payload['updated_by'] = $user->id;
        }

        $customer->update($payload);

        return $this->success($customer->fresh(), 'Customer updated successfully');
    }

    /**
     * Remove the specified customer (soft delete).
     */
    public function destroy($id): JsonResponse
    {
        $customer = AccountsCustomer::find($id);

        if (!$customer) {
            return $this->error('Customer not found', 404);
        }

        $customer->delete();

        return $this->success(null, 'Customer deleted successfully');
    }

    /**
     * Dropdown reference form options for the Customer UI.
     */
    public function formOptions(): JsonResponse
    {
        return $this->success([
            'salutations' => [
                ['value' => 'Mr.', 'label' => 'Mr.'],
                ['value' => 'Mrs.', 'label' => 'Mrs.'],
                ['value' => 'Ms.', 'label' => 'Ms.'],
                ['value' => 'Miss', 'label' => 'Miss'],
                ['value' => 'Dr.', 'label' => 'Dr.'],
            ],
            'customer_types' => [
                ['value' => 'Business', 'label' => 'Business'],
                ['value' => 'Individual', 'label' => 'Individual'],
            ],
            'currencies' => [
                ['value' => 'INR- Indian Rupee', 'label' => 'INR — Indian Rupee', 'code' => 'INR', 'symbol' => '₹'],
                ['value' => 'USD- US Dollar', 'label' => 'USD — US Dollar', 'code' => 'USD', 'symbol' => '$'],
                ['value' => 'EUR- Euro', 'label' => 'EUR — Euro', 'code' => 'EUR', 'symbol' => '€'],
                ['value' => 'AED- UAE Dirham', 'label' => 'AED — UAE Dirham', 'code' => 'AED', 'symbol' => 'AED'],
                ['value' => 'GBP- British Pound', 'label' => 'GBP — British Pound', 'code' => 'GBP', 'symbol' => '£'],
            ],
            'accounts_receivable' => [
                ['value' => 'Accounts Receivable', 'label' => 'Accounts Receivable'],
            ],
            'payment_terms' => [
                ['value' => 'Due on Receipt', 'label' => 'Due on receipt'],
                ['value' => 'Net 15', 'label' => 'Net 15'],
                ['value' => 'Net 30', 'label' => 'Net 30'],
                ['value' => 'Net 45', 'label' => 'Net 45'],
                ['value' => 'Net 60', 'label' => 'Net 60'],
            ],
            'countries' => [
                'India',
                'United Arab Emirates',
                'United States',
                'Saudi Arabia',
                'Qatar',
                'Oman',
                'Kuwait',
                'Bahrain',
                'United Kingdom',
                'Canada',
                'Australia',
                'Singapore',
            ],
        ]);
    }

    /**
     * Map request input seamlessly supporting both flat and nested / camelCase and snake_case keys.
     */
    private function formatCustomerPayload(Request $request, ?AccountsCustomer $existing = null): array
    {
        // 1. Basic Information
        $displayName = $request->input('displayName') ?? $request->input('display_name') ?? $existing?->display_name;
        $customerType = $request->input('type') ?? $request->input('customer_type') ?? ($existing?->customer_type ?? 'Business');
        $customerType = ucfirst(strtolower($customerType));

        $payload = [
            'customer_type' => $customerType,
            'salutation'    => $request->input('salutation', $existing?->salutation),
            'first_name'    => $request->input('firstName') ?? $request->input('first_name', $existing?->first_name),
            'last_name'     => $request->input('lastName') ?? $request->input('last_name', $existing?->last_name),
            'company_name'  => $request->input('companyName') ?? $request->input('company_name', $existing?->company_name),
            'display_name'  => $displayName,
            'email'         => $request->input('email', $existing?->email),
            'work_phone'    => $request->input('workPhone') ?? $request->input('work_phone', $existing?->work_phone),
            'mobile'        => $request->input('mobile', $existing?->mobile),

            // Tab 1: Other details
            'pan'               => $request->input('pan', $existing?->pan),
            'currency'          => $request->input('currency', $existing?->currency ?? 'INR- Indian Rupee'),
            'receivable_account'=> $request->input('receivableAccount') ?? $request->input('receivable_account', $existing?->receivable_account ?? 'Accounts Receivable'),
            'opening_balance'   => (float) ($request->input('openingBalance') ?? $request->input('opening_balance', $existing?->opening_balance ?? 0)),
            'payment_terms'     => $request->input('paymentTerms') ?? $request->input('payment_terms', $existing?->payment_terms ?? 'Due on Receipt'),
            'portal_access'     => (bool) ($request->input('portalAccess') ?? $request->input('portal_access', $existing?->portal_access ?? false)),

            // Remarks
            'remarks'           => $request->input('remarks', $existing?->remarks),
        ];

        // Tab 2: Billing Address
        $billing = $request->input('billingAddress') ?? $request->input('billing_address') ?? [];
        if (is_array($billing)) {
            $payload['billing_attention'] = $billing['attention'] ?? $request->input('billing_attention', $existing?->billing_attention);
            $payload['billing_country']   = $billing['country'] ?? $request->input('billing_country', $existing?->billing_country);
            $payload['billing_street1']   = $billing['street1'] ?? $request->input('billing_street1', $existing?->billing_street1);
            $payload['billing_street2']   = $billing['street2'] ?? $request->input('billing_street2', $existing?->billing_street2);
            $payload['billing_city']      = $billing['city'] ?? $request->input('billing_city', $existing?->billing_city);
            $payload['billing_state']     = $billing['state'] ?? $request->input('billing_state', $existing?->billing_state);
            $payload['billing_pin_code']  = $billing['pinCode'] ?? $billing['pin_code'] ?? $request->input('billing_pin_code', $existing?->billing_pin_code);
            $payload['billing_phone']     = $billing['phone'] ?? $request->input('billing_phone', $existing?->billing_phone);
            $payload['billing_fax']       = $billing['fax'] ?? $request->input('billing_fax', $existing?->billing_fax);
        }

        // Tab 2: Shipping Address
        $shipping = $request->input('shippingAddress') ?? $request->input('shipping_address') ?? [];
        if (is_array($shipping)) {
            $payload['shipping_attention'] = $shipping['attention'] ?? $request->input('shipping_attention', $existing?->shipping_attention);
            $payload['shipping_country']   = $shipping['country'] ?? $request->input('shipping_country', $existing?->shipping_country);
            $payload['shipping_street1']   = $shipping['street1'] ?? $request->input('shipping_street1', $existing?->shipping_street1);
            $payload['shipping_street2']   = $shipping['street2'] ?? $request->input('shipping_street2', $existing?->shipping_street2);
            $payload['shipping_city']      = $shipping['city'] ?? $request->input('shipping_city', $existing?->shipping_city);
            $payload['shipping_state']     = $shipping['state'] ?? $request->input('shipping_state', $existing?->shipping_state);
            $payload['shipping_pin_code']  = $shipping['pinCode'] ?? $shipping['pin_code'] ?? $request->input('shipping_pin_code', $existing?->shipping_pin_code);
            $payload['shipping_phone']     = $shipping['phone'] ?? $request->input('shipping_phone', $existing?->shipping_phone);
            $payload['shipping_fax']       = $shipping['fax'] ?? $request->input('shipping_fax', $existing?->shipping_fax);
        }

        // Tab 3: Contact Persons
        $contactPersons = $request->input('contactPersons') ?? $request->input('contact_persons');
        if (!is_null($contactPersons)) {
            $payload['contact_persons'] = is_array($contactPersons) ? $contactPersons : [];
        }

        // Tab 4: Custom Fields
        $customFields = $request->input('customFields') ?? $request->input('custom_fields');
        if (!is_null($customFields)) {
            $payload['custom_fields'] = is_array($customFields) ? $customFields : [];
        }

        // Tab 5: Reporting Tags
        $reportingTags = $request->input('reportingTags') ?? $request->input('reporting_tags');
        if (!is_null($reportingTags)) {
            $payload['reporting_tags'] = is_array($reportingTags) ? $reportingTags : [];
        }

        return $payload;
    }
}
