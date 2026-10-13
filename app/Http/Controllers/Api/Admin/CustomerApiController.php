<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\CustomerExport;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\CRM\StoreCustomerRequest;
use App\Http\Requests\CRM\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CustomerApiController extends ApiController
{
    /**
     * Display a listing of customers with filters & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

        $perPage = (int) $request->input('per_page', 15);
        $customers = $query->paginate($perPage);

        return $this->success($customers);
    }

    /**
     * Get reference form options (dropdown values & account managers list).
     */
    public function formOptions(): JsonResponse
    {
        $accountManagers = Employee::with('user.designation', 'user.department')
            ->select('id', 'user_id', 'employee_id', 'first_name', 'last_name', 'company_email', 'personal_email')
            ->get()
            ->map(function ($emp) {
                $fullName = trim($emp->first_name . ' ' . ($emp->last_name ?? ''));
                return [
                    'id'          => $emp->id,
                    'name'        => $fullName ?: 'Employee #' . $emp->id,
                    'employee_id' => $emp->employee_id,
                    'email'       => $emp->company_email ?? $emp->personal_email,
                    'designation' => optional(optional($emp->user)->designation)->name,
                    'department'  => optional(optional($emp->user)->department)->name,
                ];
            });

        $options = [
            'customer_types' => [
                'Company',
                'Individual',
                'Enterprise',
                'SMB',
                'Government',
            ],
            'customer_statuses' => [
                'Active',
                'Inactive',
                'Blacklisted',
                'Prospect',
                'On Hold',
            ],
            'industries' => [
                'IT Services & Software',
                'Healthcare & Medical',
                'Manufacturing',
                'Financial Services',
                'Retail & E-commerce',
                'Education',
                'Real Estate & Construction',
                'Logistics & Supply Chain',
                'Government',
                'Services',
                'Other',
            ],
            'customer_categories' => [
                'Customer',
                'Reseller',
                'Distributor',
                'Partner',
                'VIP',
                'Corporate',
            ],
            'customer_sources' => [
                'Website',
                'Referral',
                'Cold Call',
                'LinkedIn',
                'Campaign',
                'Social Media',
                'Event',
                'Converted Lead',
                'Other',
            ],
            'payment_terms' => [
                'Net 7',
                'Net 15',
                'Net 30',
                'Net 45',
                'Net 60',
                'Immediate',
                'Advance Payment',
            ],
            'preferred_communications' => [
                'Email',
                'Phone',
                'WhatsApp',
                'In Person',
            ],
            'account_managers' => $accountManagers,
        ];

        return $this->success($options, 'Customer form options retrieved successfully');
    }

    /**
     * Get Customer Management KPIs / Stats.
     */
    public function stats(): JsonResponse
    {
        $totalCustomers    = Customer::count();
        $activeCustomers   = Customer::where('customer_status', 'Active')->count();
        $inactiveCustomers = Customer::where('customer_status', 'Inactive')->count();
        $totalCreditLimit  = Customer::sum('credit_limit');

        $customersByStatus = Customer::select('customer_status', DB::raw('count(*) as count'))
            ->groupBy('customer_status')
            ->pluck('count', 'customer_status');

        $customersBySource = Customer::select('customer_source', DB::raw('count(*) as count'))
            ->whereNotNull('customer_source')
            ->groupBy('customer_source')
            ->pluck('count', 'customer_source');

        $customersByCategory = Customer::select('customer_category', DB::raw('count(*) as count'))
            ->whereNotNull('customer_category')
            ->groupBy('customer_category')
            ->pluck('count', 'customer_category');

        $customersByIndustry = Customer::select('industry', DB::raw('count(*) as count'))
            ->whereNotNull('industry')
            ->groupBy('industry')
            ->pluck('count', 'industry');

        return $this->success([
            'total_customers'       => $totalCustomers,
            'active_customers'      => $activeCustomers,
            'inactive_customers'    => $inactiveCustomers,
            'total_credit_limit'    => (float) $totalCreditLimit,
            'customers_by_status'   => $customersByStatus,
            'customers_by_source'   => $customersBySource,
            'customers_by_category' => $customersByCategory,
            'customers_by_industry' => $customersByIndustry,
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = auth()->id();

        DB::beginTransaction();
        try {
            $customer = Customer::create($validated);
            DB::commit();

            return $this->success(
                $customer->load(['accountManager', 'creator']),
                'Customer created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        return $this->success(
            $customer->load(['accountManager', 'creator', 'updater'])
        );
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $validated = $request->validated();
        $validated['updated_by'] = auth()->id();

        DB::beginTransaction();
        try {
            $customer->update($validated);
            DB::commit();

            return $this->success(
                $customer->load(['accountManager', 'creator', 'updater']),
                'Customer updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update customer: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Quick status update.
     */
    public function updateStatus(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate([
            'customer_status' => 'required|string|max:50',
            'note'            => 'nullable|string',
        ]);

        $customer->customer_status = $validated['customer_status'];
        $customer->updated_by = auth()->id();

        if (!empty($validated['note'])) {
            $customer->notes = ($customer->notes ? $customer->notes . "\n" : '')
                . "[" . now()->format('Y-m-d H:i') . "] Status changed to {$validated['customer_status']}: " . $validated['note'];
        }

        $customer->save();

        return $this->success($customer->load(['accountManager']), 'Customer status updated successfully');
    }

    /**
     * Soft-delete the specified customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $customer->deleted_by = auth()->id();
        $customer->save();
        $customer->delete();

        return $this->success(null, 'Customer deleted successfully');
    }

    /**
     * Unified export endpoint: supports format=xlsx, format=csv, format=pdf.
     */
    public function export(Request $request)
    {
        $format = strtolower($request->input('format', 'xlsx'));
        if ($format === 'pdf') {
            return $this->exportPdf($request);
        }
        return $this->exportExcel($request);
    }

    /**
     * Export customers to Excel (.xlsx) or CSV.
     */
    public function exportExcel(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $customers = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($customers);
        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'crm_customers_' . now()->format('YmdHis');

        if ($format === 'csv') {
            return Excel::download(new CustomerExport($rows), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new CustomerExport($rows), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export customers to PDF.
     */
    public function exportPdf(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $customers = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($customers);
        $export = new CustomerExport($rows);
        $filename = 'crm_customers_' . now()->format('YmdHis') . '.pdf';

        $pdf = Pdf::loadView('reports.generic_pdf', [
            'data'     => $export->array(),
            'headings' => $export->headings(),
            'title'    => 'CRM Customers Report',
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    // ── Private Helpers ────────────────────────────────────────────────────────

    /**
     * Build the filtered query (shared between index and exports).
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Customer::with(['accountManager', 'creator']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%")
                    ->orWhere('contact_person_name', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%")
                    ->orWhere('tax_gst_no', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('customer_status')) {
            if (is_array($request->customer_status)) {
                $query->whereIn('customer_status', $request->customer_status);
            } else {
                $query->where('customer_status', $request->customer_status);
            }
        }

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        if ($request->filled('customer_category')) {
            $query->where('customer_category', $request->customer_category);
        }

        if ($request->filled('customer_source')) {
            $query->where('customer_source', $request->customer_source);
        }

        if ($request->filled('industry')) {
            $query->where('industry', $request->industry);
        }

        if ($request->filled('account_manager_id')) {
            $query->where('account_manager_id', $request->account_manager_id);
        }

        if ($request->filled('payment_terms')) {
            $query->where('payment_terms', $request->payment_terms);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy    = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = [
            'created_at', 'company_name', 'customer_status', 'customer_since',
            'credit_limit', 'contact_person_name',
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortOrder) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        return $query;
    }

    /**
     * Prepare flat rows for Excel / PDF export.
     */
    private function prepareExportRows($customers): array
    {
        $rows = [];
        foreach ($customers as $c) {
            $managerName = $c->accountManager
                ? trim($c->accountManager->first_name . ' ' . ($c->accountManager->last_name ?? ''))
                : 'Unassigned';

            $rows[] = [
                $c->customer_id ?? '-',
                $c->company_name ?? '-',
                $c->customer_type ?? '-',
                $c->industry ?? '-',
                $c->company_registration_no ?? '-',
                $c->tax_gst_no ?? '-',
                $c->website ?? '-',
                $c->customer_status ?? '-',
                $c->customer_since ? $c->customer_since->format('Y-m-d') : '-',
                $managerName,
                $c->billing_address ?? '-',
                $c->shipping_address ?? '-',
                $c->city ?? '-',
                $c->state ?? '-',
                $c->country ?? 'India',
                $c->postal_code ?? '-',
                $c->contact_person_name ?? '-',
                $c->contact_designation ?? '-',
                $c->contact_email ?? '-',
                $c->contact_phone ?? '-',
                $c->contact_alternate_phone ?? '-',
                $c->preferred_communication ?? '-',
                $c->customer_category ?? '-',
                $c->customer_source ?? '-',
                $c->payment_terms ?? '-',
                number_format((float) ($c->credit_limit ?? 0), 2),
                $c->created_at ? $c->created_at->format('Y-m-d H:i') : '-',
            ];
        }
        return $rows;
    }

    /**
     * Authenticate via bearer token or URL query parameter token (for browser downloads).
     */
    private function authenticateFromToken(Request $request): void
    {
        if (!$request->bearerToken() && $request->has('token')) {
            try {
                $user = auth('api')->setToken($request->token)->user();
                if ($user) {
                    auth('api')->setUser($user);
                }
            } catch (\Exception $e) {
                // Fail silently
            }
        }

        if (!auth('api')->check()) {
            abort(403, 'Forbidden - Access denied. Please provide a valid token.');
        }
    }
}
