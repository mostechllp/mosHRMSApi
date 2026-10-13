<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\LeadExport;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\CRM\StoreLeadRequest;
use App\Http\Requests\CRM\UpdateLeadRequest;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LeadApiController extends ApiController
{
    /**
     * Display a listing of leads with filters & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

        $perPage = (int) $request->input('per_page', 15);
        $leads = $query->paginate($perPage);

        return $this->success($leads);
    }

    /**
     * Export leads to Excel (.xlsx or .csv).
     */
    public function exportExcel(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $leads = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($leads);

        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'crm_leads_' . now()->format('YmdHis');

        if ($format === 'csv') {
            return Excel::download(new LeadExport($rows), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new LeadExport($rows), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export leads to PDF document.
     */
    public function exportPdf(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $leads = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($leads);

        $export = new LeadExport($rows);
        $filename = 'crm_leads_' . now()->format('YmdHis') . '.pdf';

        $pdf = Pdf::loadView('reports.generic_pdf', [
            'data'     => $export->array(),
            'headings' => $export->headings(),
            'title'    => 'CRM Leads Report',
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Unified Export Endpoint (supports format=xlsx, format=csv, format=pdf).
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
     * Get reference form options (dropdown values & salespersons list).
     */
    public function formOptions(): JsonResponse
    {
        $salespersons = Employee::with('user.designation', 'user.department')
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

        $existingTeams = Lead::whereNotNull('sales_team')
            ->select('sales_team')
            ->distinct()
            ->pluck('sales_team')
            ->toArray();

        $defaultTeams = ['Sales Team Alpha', 'Enterprise Sales', 'SMB Sales', 'Global Sales'];
        $salesTeams = array_values(array_unique(array_merge($defaultTeams, $existingTeams)));

        $options = [
            'lead_types' => [
                'Company',
                'Individual',
                'Enterprise',
                'SMB',
            ],
            'lead_sources' => [
                'Website',
                'Referral',
                'Cold Call',
                'LinkedIn',
                'Campaign',
                'Social Media',
                'Event',
                'Other',
            ],
            'lead_statuses' => [
                'New',
                'Contacted',
                'Working',
                'Qualified',
                'Unqualified',
                'Converted',
                'Lost',
            ],
            'priorities' => [
                'Low',
                'Medium',
                'High',
                'Urgent',
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
                'Services',
                'Other',
            ],
            'interested_products' => [
                'HRMS',
                'ERP',
                'CRM',
                'Payroll',
                'Attendance System',
                'Custom Development',
            ],
            'follow_up_types' => [
                'Call',
                'Meeting',
                'Email',
                'Demo',
                'Task',
            ],
            'salespersons' => $salespersons,
            'sales_teams'  => $salesTeams,
        ];

        return $this->success($options, 'Lead form options retrieved successfully');
    }

    /**
     * Get CRM summary statistics and KPIs.
     */
    public function stats(): JsonResponse
    {
        $totalLeads = Lead::count();
        $newLeads = Lead::where('lead_status', 'New')->count();
        $qualifiedLeads = Lead::where('lead_status', 'Qualified')->count();
        $convertedLeads = Lead::where('lead_status', 'Converted')->count();
        $totalExpectedValue = Lead::sum('expected_value');
        $pendingFollowUpsCount = Lead::whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '>=', now())
            ->count();

        $leadsByStatus = Lead::select('lead_status', DB::raw('count(*) as count'))
            ->groupBy('lead_status')
            ->pluck('count', 'lead_status');

        $leadsBySource = Lead::select('lead_source', DB::raw('count(*) as count'))
            ->whereNotNull('lead_source')
            ->groupBy('lead_source')
            ->pluck('count', 'lead_source');

        $leadsByPriority = Lead::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return $this->success([
            'total_leads'              => $totalLeads,
            'new_leads'                => $newLeads,
            'qualified_leads'          => $qualifiedLeads,
            'converted_leads'          => $convertedLeads,
            'total_expected_value'     => (float) $totalExpectedValue,
            'pending_follow_ups_count' => $pendingFollowUpsCount,
            'leads_by_status'          => $leadsByStatus,
            'leads_by_source'          => $leadsBySource,
            'leads_by_priority'        => $leadsByPriority,
        ]);
    }

    /**
     * Store a newly created lead in storage.
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = auth()->id();

        DB::beginTransaction();
        try {
            $lead = Lead::create($validated);

            // If follow-up date is provided, create initial activity log
            if (!empty($validated['next_follow_up_at'])) {
                LeadFollowUp::create([
                    'lead_id'        => $lead->id,
                    'follow_up_type' => $validated['follow_up_type'] ?? 'Call',
                    'follow_up_date' => $validated['next_follow_up_at'],
                    'status'         => 'scheduled',
                    'notes'          => $validated['follow_up_notes'] ?? null,
                    'performed_by'   => $lead->assigned_salesperson_id,
                    'created_by'     => auth()->id(),
                ]);
            }

            DB::commit();

            return $this->success(
                $lead->load(['assignedSalesperson', 'followUps', 'creator']),
                'Lead created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create lead: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified lead.
     */
    public function show(Lead $lead): JsonResponse
    {
        return $this->success($lead->load(['assignedSalesperson', 'followUps.performer', 'creator', 'updater']));
    }

    /**
     * Update the specified lead in storage.
     */
    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $validated = $request->validated();
        $validated['updated_by'] = auth()->id();

        DB::beginTransaction();
        try {
            $lead->update($validated);

            // If next_follow_up_at is changed or passed in update, create or update schedule activity log
            if ($request->has('next_follow_up_at') && !empty($validated['next_follow_up_at'])) {
                LeadFollowUp::create([
                    'lead_id'        => $lead->id,
                    'follow_up_type' => $validated['follow_up_type'] ?? $lead->follow_up_type ?? 'Call',
                    'follow_up_date' => $validated['next_follow_up_at'],
                    'status'         => 'scheduled',
                    'notes'          => $validated['follow_up_notes'] ?? null,
                    'performed_by'   => $lead->assigned_salesperson_id,
                    'created_by'     => auth()->id(),
                ]);
            }

            DB::commit();

            return $this->success(
                $lead->load(['assignedSalesperson', 'followUps.performer', 'creator', 'updater']),
                'Lead updated successfully'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update lead: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Quick status update for a lead.
     */
    public function updateStatus(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'lead_status' => 'required|string|max:100',
            'note'        => 'nullable|string',
        ]);

        $lead->lead_status = $validated['lead_status'];
        $lead->updated_by = auth()->id();

        if (!empty($validated['note'])) {
            $lead->notes = ($lead->notes ? $lead->notes . "\n" : '') . "[" . now()->format('Y-m-d H:i') . "] Status changed to {$validated['lead_status']}: " . $validated['note'];
        }

        $lead->save();

        return $this->success($lead->load(['assignedSalesperson']), 'Lead status updated successfully');
    }

    /**
     * Quick action to convert a lead.
     */
    public function convert(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'converted_value' => 'nullable|numeric|min:0',
            'conversion_note' => 'nullable|string',
        ]);

        $lead->lead_status = 'Converted';
        if (isset($validated['converted_value'])) {
            $lead->expected_value = $validated['converted_value'];
        }
        if (!empty($validated['conversion_note'])) {
            $lead->notes = ($lead->notes ? $lead->notes . "\n" : '') . "[" . now()->format('Y-m-d H:i') . "] Lead Converted: " . $validated['conversion_note'];
        }
        $lead->updated_by = auth()->id();
        $lead->save();

        return $this->success($lead->load(['assignedSalesperson']), 'Lead converted successfully');
    }

    /**
     * Remove the specified lead from storage.
     */
    public function destroy(Lead $lead): JsonResponse
    {
        $lead->deleted_by = auth()->id();
        $lead->save();
        $lead->delete();

        return $this->success(null, 'Lead deleted successfully');
    }

    /**
     * Helper: Build filtered query for leads.
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Lead::with(['assignedSalesperson', 'creator']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('lead_name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('primary_phone', 'like', "%{$search}%")
                    ->orWhere('lead_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('lead_status')) {
            if (is_array($request->lead_status)) {
                $query->whereIn('lead_status', $request->lead_status);
            } else {
                $query->where('lead_status', $request->lead_status);
            }
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('lead_source')) {
            $query->where('lead_source', $request->lead_source);
        }

        if ($request->filled('lead_type')) {
            $query->where('lead_type', $request->lead_type);
        }

        if ($request->filled('assigned_salesperson_id')) {
            $query->where('assigned_salesperson_id', $request->assigned_salesperson_id);
        }

        if ($request->filled('sales_team')) {
            $query->where('sales_team', $request->sales_team);
        }

        if ($request->filled('industry')) {
            $query->where('industry', $request->industry);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['created_at', 'lead_name', 'company_name', 'expected_value', 'next_follow_up_at', 'lead_status', 'priority'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortOrder) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        return $query;
    }

    /**
     * Helper: Prepare rows for export.
     */
    private function prepareExportRows($leads): array
    {
        $rows = [];
        foreach ($leads as $lead) {
            $salespersonName = $lead->assignedSalesperson
                ? trim($lead->assignedSalesperson->first_name . ' ' . ($lead->assignedSalesperson->last_name ?? ''))
                : 'Unassigned';

            $interestedProducts = is_array($lead->interested_products)
                ? implode(', ', $lead->interested_products)
                : ($lead->interested_products ?? '-');

            $rows[] = [
                $lead->lead_id ?? '-',
                $lead->lead_name ?? '-',
                $lead->lead_type ?? '-',
                $lead->company_name ?? '-',
                $lead->designation ?? '-',
                $lead->industry ?? '-',
                $lead->lead_source ?? '-',
                $lead->lead_status ?? '-',
                $lead->priority ?? '-',
                $lead->email ?? '-',
                $lead->primary_phone ?? '-',
                $lead->city ?? '-',
                $lead->state ?? '-',
                $lead->country ?? 'India',
                $salespersonName,
                $lead->sales_team ?? '-',
                number_format((float) ($lead->expected_value ?? 0), 2),
                $lead->expected_closing_date ? $lead->expected_closing_date->format('Y-m-d') : '-',
                $interestedProducts,
                $lead->next_follow_up_at ? $lead->next_follow_up_at->format('Y-m-d H:i') : '-',
                $lead->created_at ? $lead->created_at->format('Y-m-d H:i') : '-',
            ];
        }
        return $rows;
    }

    /**
     * Helper: Authenticate via bearer token or URL query parameter token.
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
