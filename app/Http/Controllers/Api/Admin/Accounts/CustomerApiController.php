<?php

namespace App\Http\Controllers\Api\Admin\Accounts;

use App\Exports\AccountsCustomerExport;
use App\Http\Controllers\Api\ApiController;
use App\Imports\AccountsCustomerImport;
use App\Models\AccountsCustomer;
use App\Models\AccountsCustomerContactPerson;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CustomerApiController extends ApiController
{
    /**
     * Display a listing of accounts customers with search, filters & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AccountsCustomer::query()->latest();

        try {
            $query->with('contactPersons');
        } catch (\Throwable $e) {
            // Safe fallback if relation table is not yet migrated
        }

        // Multi-tenant check
        $user = auth('api')->user();
        if ($user && $user->company_id) {
            $query->where(function ($q) use ($user) {
                $q->where('company_id', $user->company_id)
                    ->orWhereNull('company_id');
            });
        }

        // Search by name, company, email, phone, country
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('work_phone', 'like', "%{$search}%");
            });
        }

        // Filter by Customer Type (Business / Individual)
        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        // Filter by Status (active / inactive)
        if ($request->filled('status')) {
            $query->where('status', strtolower($request->status));
        }

        // Filter by Source (user / import)
        if ($request->filled('source')) {
            $query->where('source', strtolower($request->source));
        }

        $perPage = max(1, (int) $request->input('per_page', 15));
        $customers = $query->paginate($perPage);

        return $this->success($customers);
    }

    /**
     * Store a newly created customer.
     * Note: display_name is a text box (required).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'display_name' => 'required_without:displayName|string|max:255',
            'displayName' => 'nullable|string|max:255',
            'type' => 'nullable|in:Business,Individual,business,individual',
            'customer_type' => 'nullable|in:Business,Individual,business,individual',
            'salutation' => 'nullable|string|max:20',
            'first_name' => 'nullable|string|max:100',
            'firstName' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'lastName' => 'nullable|string|max:100',
            'company_name' => 'nullable|string|max:255',
            'companyName' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'country' => 'nullable|string|max:100',
            'work_phone' => 'nullable|string|max:50',
            'workPhone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'gstin_pan' => 'nullable|string|max:50',
            'gstinPan' => 'nullable|string|max:50',
            'pan' => 'nullable|string|max:50',
            'currencies' => 'nullable|array',
            'base_currency' => 'nullable|string|max:20',
            'baseCurrency' => 'nullable|string|max:20',
            'exchange_rates' => 'nullable|array',
            'exchangeRates' => 'nullable|array',
            'opening_balance' => 'nullable|numeric',
            'openingBalance' => 'nullable|numeric',
            'unused_credits' => 'nullable|numeric',
            'unusedCredits' => 'nullable|numeric',
            'source' => 'nullable|string|max:50',
            'status' => 'nullable|in:active,inactive,Active,Inactive',
            'payment_terms' => 'nullable|string|max:100',
            'paymentTerms' => 'nullable|string|max:100',
            'portal_access' => 'nullable|boolean',
            'portalAccess' => 'nullable|boolean',
            'remarks' => 'nullable|string',
            'billingAddress' => 'nullable|array',
            'shippingAddress' => 'nullable|array',
            'billing_address' => 'nullable|array',
            'shipping_address' => 'nullable|array',
            'contactPersons' => 'nullable|array',
            'contact_persons' => 'nullable|array',
        ]);

        $payload = $this->formatCustomerPayload($request);

        $user = auth('api')->user();
        if ($user) {
            $payload['created_by'] = $user->id;
            $payload['company_id'] = $user->company_id ?? null;
        }

        $customer = AccountsCustomer::create($payload);

        // Save Contact Persons if provided
        $contactPersonsList = $request->input('contactPersons') ?? $request->input('contact_persons');
        if (is_array($contactPersonsList) && count($contactPersonsList) > 0) {
            $this->syncContactPersons($customer, $contactPersonsList);
        }

        try {
            $customer->load('contactPersons');
        } catch (\Throwable $e) {
        }

        return $this->success($customer, 'Customer created successfully', 201);
    }

    /**
     * Display the specified customer.
     */
    public function show($id): JsonResponse
    {
        try {
            $customer = AccountsCustomer::with(['contactPersons', 'company', 'creator', 'updater'])->find($id);
        } catch (\Throwable $e) {
            $customer = AccountsCustomer::with(['company', 'creator', 'updater'])->find($id);
        }

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
            'displayName' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'country' => 'nullable|string|max:100',
            'type' => 'nullable|in:Business,Individual,business,individual',
            'customer_type' => 'nullable|in:Business,Individual,business,individual',
        ]);

        $payload = $this->formatCustomerPayload($request, $customer);

        $user = auth('api')->user();
        if ($user) {
            $payload['updated_by'] = $user->id;
        }

        $customer->update($payload);

        // Sync contact persons if provided
        $contactPersonsList = $request->input('contactPersons') ?? $request->input('contact_persons');
        if (is_array($contactPersonsList)) {
            $this->syncContactPersons($customer, $contactPersonsList);
        }

        $fresh = $customer->fresh();
        try {
            $fresh->load('contactPersons');
        } catch (\Throwable $e) {
        }

        return $this->success($fresh, 'Customer updated successfully');
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

        // Delete associated contact persons
        $customer->contactPersons()->delete();
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
                ['code' => 'INR', 'label' => 'INR', 'symbol' => '₹', 'name' => 'Indian Rupee'],
                ['code' => 'USD', 'label' => 'USD', 'symbol' => '$', 'name' => 'US Dollar'],
                ['code' => 'EUR', 'label' => 'EUR', 'symbol' => '€', 'name' => 'Euro'],
                ['code' => 'GBP', 'label' => 'GBP', 'symbol' => '£', 'name' => 'British Pound'],
                ['code' => 'AED', 'label' => 'AED', 'symbol' => 'AED', 'name' => 'UAE Dirham'],
                ['code' => 'SAR', 'label' => 'SAR', 'symbol' => 'SAR', 'name' => 'Saudi Riyal'],
                ['code' => 'JPY', 'label' => 'JPY', 'symbol' => '¥', 'name' => 'Japanese Yen'],
                ['code' => 'RUB', 'label' => 'RUB', 'symbol' => '₽', 'name' => 'Russian Ruble'],
                ['code' => 'CNY', 'label' => 'CNY', 'symbol' => '¥', 'name' => 'Chinese Yuan'],
            ],
            'base_currencies' => [
                ['value' => 'INR', 'label' => 'INR — Indian Rupee'],
                ['value' => 'USD', 'label' => 'USD — US Dollar'],
                ['value' => 'EUR', 'label' => 'EUR — Euro'],
                ['value' => 'GBP', 'label' => 'GBP — British Pound'],
                ['value' => 'AED', 'label' => 'AED — UAE Dirham'],
                ['value' => 'SAR', 'label' => 'SAR — Saudi Riyal'],
                ['value' => 'JPY', 'label' => 'JPY — Japanese Yen'],
                ['value' => 'RUB', 'label' => 'RUB — Russian Ruble'],
                ['value' => 'CNY', 'label' => 'CNY — Chinese Yuan'],
            ],
            'default_exchange_rates' => [
                'USD' => 0.00,
                'EUR' => 0.00,
                'GBP' => 0.00,
                'AED' => 0.00,
                'SAR' => 0.00,
                'JPY' => 0.00,
                'RUB' => 0.00,
                'CNY' => 0.00,
            ],
            'accounts_receivable' => [
                ['value' => 'Accounts Receivable', 'label' => 'Accounts Receivable'],
            ],
            'payment_terms' => [
                ['value' => 'Due on receipt', 'label' => 'Due on receipt'],
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
                'China',
                'Japan',
                'Russia',
                'Germany',
                'France',
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

        $country = $request->input('country', $existing?->country ?? 'India');

        $gstinPan = $request->input('gstinPan')
            ?? $request->input('gstin_pan')
            ?? $request->input('pan')
            ?? $existing?->gstin_pan;

        $currencies = $request->input('currencies');
        if (!is_null($currencies)) {
            $currencies = is_array($currencies) ? $currencies : [$currencies];
        } else {
            $currencies = $existing?->currencies ?? ['INR'];
        }

        $baseCurrency = $request->input('baseCurrency')
            ?? $request->input('base_currency')
            ?? $existing?->base_currency
            ?? 'INR';

        $exchangeRates = $request->input('exchangeRates')
            ?? $request->input('exchange_rates')
            ?? $existing?->exchange_rates
            ?? [
                'USD' => 0.00,
                'EUR' => 0.00,
                'GBP' => 0.00,
                'AED' => 0.00,
                'SAR' => 0.00,
                'JPY' => 0.00,
                'RUB' => 0.00,
                'CNY' => 0.00,
            ];

        $payload = [
            'customer_type' => $customerType,
            'salutation' => $request->input('salutation', $existing?->salutation),
            'first_name' => $request->input('firstName') ?? $request->input('first_name', $existing?->first_name),
            'last_name' => $request->input('lastName') ?? $request->input('last_name', $existing?->last_name),
            'company_name' => $request->input('companyName') ?? $request->input('company_name', $existing?->company_name),
            'display_name' => $displayName,
            'email' => $request->input('email', $existing?->email),
            'country' => $country,
            'work_phone' => $request->input('workPhone') ?? $request->input('work_phone', $existing?->work_phone),
            'mobile' => $request->input('mobile', $existing?->mobile),

            // Tab 1: Other details
            'gstin_pan' => $gstinPan,
            'pan' => $gstinPan,
            'currencies' => $currencies,
            'base_currency' => $baseCurrency,
            'exchange_rates' => $exchangeRates,
            'receivable_account' => $request->input('receivableAccount') ?? $request->input('receivable_account', $existing?->receivable_account ?? 'Accounts Receivable'),
            'opening_balance' => (float) ($request->input('openingBalance') ?? $request->input('opening_balance', $existing?->opening_balance ?? 0)),
            'unused_credits' => (float) ($request->input('unusedCredits') ?? $request->input('unused_credits', $existing?->unused_credits ?? 0)),
            'source' => $request->input('source', $existing?->source ?? 'user'),
            'status' => strtolower($request->input('status', $existing?->status ?? 'active')) === 'inactive' ? 'inactive' : 'active',
            'payment_terms' => $request->input('paymentTerms') ?? $request->input('payment_terms', $existing?->payment_terms ?? 'Due on receipt'),
            'portal_access' => (bool) ($request->input('portalAccess') ?? $request->input('portal_access', $existing?->portal_access ?? false)),

            // Tab 4: Remarks
            'remarks' => $request->input('remarks', $existing?->remarks),
        ];

        // Tab 2: Billing Address
        $billing = $request->input('billingAddress') ?? $request->input('billing_address') ?? [];
        if (is_array($billing)) {
            $payload['billing_attention'] = $billing['attention'] ?? $request->input('billing_attention', $existing?->billing_attention);
            $payload['billing_country'] = $billing['country'] ?? $country;
            $payload['billing_street1'] = $billing['street1'] ?? $request->input('billing_street1', $existing?->billing_street1);
            $payload['billing_street2'] = $billing['street2'] ?? $request->input('billing_street2', $existing?->billing_street2);
            $payload['billing_city'] = $billing['city'] ?? $request->input('billing_city', $existing?->billing_city);
            $payload['billing_state'] = $billing['state'] ?? $request->input('billing_state', $existing?->billing_state);
            $payload['billing_pin_code'] = $billing['pinCode'] ?? $billing['pin_code'] ?? $request->input('billing_pin_code', $existing?->billing_pin_code);
            $payload['billing_phone'] = $billing['phone'] ?? $request->input('billing_phone', $existing?->billing_phone);
            $payload['billing_fax'] = $billing['fax'] ?? $request->input('billing_fax', $existing?->billing_fax);
        }

        // Tab 2: Shipping Address
        $shipping = $request->input('shippingAddress') ?? $request->input('shipping_address') ?? [];
        if (is_array($shipping)) {
            $payload['shipping_attention'] = $shipping['attention'] ?? $request->input('shipping_attention', $existing?->shipping_attention);
            $payload['shipping_country'] = $shipping['country'] ?? $country;
            $payload['shipping_street1'] = $shipping['street1'] ?? $request->input('shipping_street1', $existing?->shipping_street1);
            $payload['shipping_street2'] = $shipping['street2'] ?? $request->input('shipping_street2', $existing?->shipping_street2);
            $payload['shipping_city'] = $shipping['city'] ?? $request->input('shipping_city', $existing?->shipping_city);
            $payload['shipping_state'] = $shipping['state'] ?? $request->input('shipping_state', $existing?->shipping_state);
            $payload['shipping_pin_code'] = $shipping['pinCode'] ?? $shipping['pin_code'] ?? $request->input('shipping_pin_code', $existing?->shipping_pin_code);
            $payload['shipping_phone'] = $shipping['phone'] ?? $request->input('shipping_phone', $existing?->shipping_phone);
            $payload['shipping_fax'] = $shipping['fax'] ?? $request->input('shipping_fax', $existing?->shipping_fax);
        }

        return $payload;
    }

    /**
     * Synchronize contact persons table and customer JSON backup.
     */
    private function syncContactPersons(AccountsCustomer $customer, array $persons): void
    {
        $savedPersons = [];

        foreach ($persons as $p) {
            if (empty($p) || (!isset($p['firstName']) && !isset($p['first_name']) && !isset($p['email']))) {
                continue;
            }

            $personData = [
                'salutation' => $p['salutation'] ?? null,
                'first_name' => $p['firstName'] ?? ($p['first_name'] ?? null),
                'last_name' => $p['lastName'] ?? ($p['last_name'] ?? null),
                'email' => $p['email'] ?? null,
                'work_phone' => $p['workPhone'] ?? ($p['work_phone'] ?? null),
                'mobile' => $p['mobile'] ?? null,
            ];

            if (!empty($p['id'])) {
                $contact = AccountsCustomerContactPerson::where('customer_id', $customer->id)->find($p['id']);
                if ($contact) {
                    $contact->update($personData);
                    $savedPersons[] = $contact->toArray();
                    continue;
                }
            }

            $contact = $customer->contactPersons()->create($personData);
            $savedPersons[] = $contact->toArray();
        }

        $customer->update(['contact_persons' => $savedPersons]);
    }

    /**
     * Unified Export for Accounts Customers (supports format=xlsx, csv, pdf).
     */
    public function export(Request $request)
    {
        $this->authenticateFromToken($request);

        $format = strtolower($request->input('format', 'xlsx'));
        if ($format === 'pdf') {
            return $this->exportPdf($request);
        }

        return $this->exportExcel($request);
    }

    /**
     * Export Accounts Customers to Excel or CSV.
     */
    public function exportExcel(Request $request)
    {
        $this->authenticateFromToken($request);

        $customers = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($customers);
        $filename = 'accounts_customers_' . now()->format('YmdHis');

        $format = strtolower($request->input('format', 'xlsx'));
        if ($format === 'csv') {
            return Excel::download(new AccountsCustomerExport($rows), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new AccountsCustomerExport($rows), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export Accounts Customers to PDF.
     * Supports passing specific headings/columns via query parameter 'columns' (e.g., columns[]=Name&columns[]=Email)
     * to avoid table congestion.
     */
    public function exportPdf(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $customers = $this->buildFilteredQuery($request)->get();
        $requestedColumns = $request->input('columns');

        if (!empty($requestedColumns) && is_array($requestedColumns)) {
            [$headings, $rows] = $this->prepareCustomPdfRows($customers, $requestedColumns);
        } else {
            $export = new AccountsCustomerExport($this->prepareExportRows($customers));
            $headings = $export->headings();
            $rows = $export->array();
        }

        $filename = 'accounts_customers_' . now()->format('YmdHis') . '.pdf';

        $pdf = Pdf::loadView('reports.generic_pdf', [
            'data' => $rows,
            'headings' => $headings,
            'title' => 'Accounts Customers Report',
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Import Accounts Customers from an Excel or CSV file.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $user = auth('api')->user();
        $companyId = $user?->company_id;
        $createdBy = $user?->id;

        $importer = new AccountsCustomerImport($companyId, $createdBy);

        try {
            Excel::import($importer, $request->file('file'));

            return $this->success([
                'imported_count' => $importer->getImportedCount(),
                'skipped_count' => $importer->getSkippedCount(),
                'errors' => $importer->getErrors(),
            ], 'Customers imported successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to import customers: ' . $e->getMessage(), 422);
        }
    }

    /**
     * Build filtered query for list and exports.
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = AccountsCustomer::query()->latest();

        try {
            $query->with('contactPersons');
        } catch (\Throwable $e) {
            // fallback
        }

        $user = auth('api')->user();
        if ($user && $user->company_id) {
            $query->where(function ($q) use ($user) {
                $q->where('company_id', $user->company_id)
                    ->orWhereNull('company_id');
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('work_phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        if ($request->filled('status')) {
            $query->where('status', strtolower($request->status));
        }

        if ($request->filled('source')) {
            $query->where('source', strtolower($request->source));
        }

        return $query;
    }

    /**
     * Flatten customers list into export rows.
     */
    private function prepareExportRows($customers): array
    {
        $rows = [];
        foreach ($customers as $c) {
            $rows[] = [
                $c->customer_type ?? 'Business',
                $c->display_name ?? '-',
                $c->salutation ?? '',
                $c->first_name ?? '',
                $c->last_name ?? '',
                $c->company_name ?? '',
                $c->email ?? '',
                $c->country ?? 'India',
                $c->work_phone ?? '',
                $c->mobile ?? '',
                $c->gstin_pan ?? '',
                $c->base_currency ?? 'INR',
                $c->opening_balance ?? 0,
                $c->payment_terms ?? 'Due on receipt',
                $c->status ?? 'active',
                $c->source ?? 'user',
                $c->billing_attention ?? '',
                $c->billing_country ?? '',
                $c->billing_street1 ?? '',
                $c->billing_street2 ?? '',
                $c->billing_city ?? '',
                $c->billing_state ?? '',
                $c->billing_pin_code ?? '',
                $c->billing_phone ?? '',
                $c->shipping_attention ?? '',
                $c->shipping_country ?? '',
                $c->shipping_street1 ?? '',
                $c->shipping_street2 ?? '',
                $c->shipping_city ?? '',
                $c->shipping_state ?? '',
                $c->shipping_pin_code ?? '',
                $c->shipping_phone ?? '',
                $c->remarks ?? '',
            ];
        }
        return $rows;
    }

    /**
     * Map requested dynamic columns to customer fields for PDF generation.
     */
    private function prepareCustomPdfRows($customers, array $requestedColumns): array
    {
        $headings = [];
        $columnKeys = [];

        foreach ($requestedColumns as $col) {
            $colName = trim((string) $col);
            if ($colName === '') {
                continue;
            }
            $headings[] = $colName;
            $columnKeys[] = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $colName));
        }

        if (empty($headings)) {
            $export = new AccountsCustomerExport($this->prepareExportRows($customers));
            return [$export->headings(), $export->array()];
        }

        $rows = [];
        foreach ($customers as $c) {
            $currencyCode = strtoupper($c->base_currency ?? 'INR');
            $currencySymbol = $this->getCurrencySymbol($currencyCode);

            $receivablesVal = (float) ($c->opening_balance ?? 0);
            $unusedCreditsVal = (float) ($c->unused_credits ?? 0);

            $row = [];
            foreach ($columnKeys as $normKey) {
                $row[] = match ($normKey) {
                    'name', 'displayname', 'customername' => $c->display_name ?? '-',
                    'firstname' => $c->first_name ?? '',
                    'lastname' => $c->last_name ?? '',
                    'email', 'emailaddress' => $c->email ?? '',
                    'phone', 'workphone' => $c->work_phone ?? ($c->mobile ?? ''),
                    'mobile', 'mobilephone' => $c->mobile ?? '',
                    'companyname', 'company' => $c->company_name ?? '',
                    'receivablesbcy', 'receivables' => $currencySymbol . number_format($receivablesVal, 2),
                    'unusedcreditsbcy', 'unusedcredits' => $currencySymbol . number_format($unusedCreditsVal, 2),
                    'source', 'customersource' => $c->source ?? ($c->customer_source ?? 'user'),
                    'status', 'customerstatus' => $c->status ?? 'active',
                    'salutation' => $c->salutation ?? '',
                    'customertype', 'type' => $c->customer_type ?? 'Business',
                    'taxidpan', 'taxid', 'pan', 'gstinpan', 'gstin' => $c->gstin_pan ?? ($c->pan ?? ''),
                    'currency', 'basecurrency' => $currencyCode,
                    'paymentterms' => $c->payment_terms ?? 'Due on receipt',
                    'portalaccess' => $c->portal_access ? 'Yes' : 'No',
                    'openingbalance' => $currencySymbol . number_format($receivablesVal, 2),
                    'receivableaccount' => $c->receivable_account ?? 'Accounts Receivable',
                    'createdat', 'created' => $c->created_at ? $c->created_at->format('Y-m-d') : '-',
                    'country' => $c->country ?? 'India',
                    'billingaddress' => trim(($c->billing_street1 ?? '') . ' ' . ($c->billing_city ?? '') . ' ' . ($c->billing_state ?? '')),
                    'shippingaddress' => trim(($c->shipping_street1 ?? '') . ' ' . ($c->shipping_city ?? '') . ' ' . ($c->shipping_state ?? '')),
                    'remarks', 'notes', 'internalnotes' => $c->remarks ?? '',
                    default => $c->{$normKey} ?? ($c->getAttribute($normKey) ?? '-')
                };
            }
            $rows[] = $row;
        }

        return [$headings, $rows];
    }

    /**
     * Helper to return currency symbol based on base currency code.
     */
    private function getCurrencySymbol(?string $currencyCode): string
    {
        $code = strtoupper(trim((string) $currencyCode));

        return match ($code) {
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'AED ',
            'SAR' => 'SAR ',
            'JPY' => '¥',
            'RUB' => '₽',
            'CNY' => '¥',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            'SGD' => 'S$',
            default => $code ? ($code . ' ') : '₹',
        };
    }

    /**
     * Authenticate via bearer token or URL query token for exports.
     */
    private function authenticateFromToken(Request $request): void
    {
        if (!$request->bearerToken() && $request->has('token')) {
            try {
                $user = auth('api')->setToken($request->token)->user();
                if ($user) {
                    auth('api')->setUser($user);
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        if (!auth('api')->check()) {
            abort(403, 'Forbidden - Access denied. Please provide a valid token.');
        }
    }
}
