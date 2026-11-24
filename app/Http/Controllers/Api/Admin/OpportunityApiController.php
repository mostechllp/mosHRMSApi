<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\OpportunityExport;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\CRM\StoreOpportunityRequest;
use App\Http\Requests\CRM\UpdateOpportunityRequest;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\LeadPriority;
use App\Models\LeadSource;
use App\Models\Opportunity;
use App\Models\OpportunityAttachment;
use App\Models\OpportunityLostReason;
use App\Models\OpportunityStage;
use App\Models\OpportunityType;
use App\Models\OpportunityWinReason;
use App\Models\Product;
use App\Models\ProductCurrency;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class OpportunityApiController extends ApiController
{
    /**
     * Display a listing of opportunities with extensive filtering & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

        $perPage = (int) $request->input('per_page', 15);
        $opportunities = $query->paginate($perPage);

        return $this->success($opportunities, 'Opportunities retrieved successfully');
    }

    /**
     * Group opportunities by stage for Kanban / Pipeline view.
     */
    public function pipeline(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);
        $opportunities = $query->get();

        $stages = [
            'New' => ['color' => '#1D4ED8', 'bg' => '#EFF6FF'],
            'Qualified' => ['color' => '#7E22CE', 'bg' => '#F3E8FF'],
            'Proposal Sent' => ['color' => '#4338CA', 'bg' => '#EEF2FF'],
            'Negotiation' => ['color' => '#B45309', 'bg' => '#FEF3C7'],
            'Won' => ['color' => '#047857', 'bg' => '#ECFDF5'],
            'Lost' => ['color' => '#B91C1C', 'bg' => '#FEE2E2'],
        ];

        $pipeline = [];
        foreach ($stages as $stageName => $meta) {
            $items = $opportunities->filter(function ($item) use ($stageName) {
                return strcasecmp(trim($item->stage), trim($stageName)) === 0;
            })->values();

            $totalValue = (float) $items->sum('estimated_value');

            $pipeline[] = [
                'stage' => $stageName,
                'color' => $meta['color'],
                'bg' => $meta['bg'],
                'count' => $items->count(),
                'total_value' => $totalValue,
                'formatted_value' => $this->formatIndianValue($totalValue),
                'opportunities' => $items,
            ];
        }

        return $this->success($pipeline, 'Pipeline data retrieved successfully');
    }

    /**
     * Get Opportunity summary statistics and KPIs for the top cards.
     */
    public function stats(): JsonResponse
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();

        $totalOpportunities = Opportunity::count();
        $openOpportunities = Opportunity::open()->count();
        $pipelineValue = (float) Opportunity::open()->sum('estimated_value');

        $wonCount = Opportunity::where('stage', 'Won')->count();
        $wonValue = (float) Opportunity::where('stage', 'Won')->sum('estimated_value');

        $lostCount = Opportunity::where('stage', 'Lost')->count();
        $lostValue = (float) Opportunity::where('stage', 'Lost')->sum('estimated_value');

        $closingThisMonth = Opportunity::whereNotNull('expected_closing_date')
            ->whereBetween('expected_closing_date', [$startOfMonth, $endOfMonth])
            ->count();

        $closingThisMonthValue = (float) Opportunity::whereNotNull('expected_closing_date')
            ->whereBetween('expected_closing_date', [$startOfMonth, $endOfMonth])
            ->sum('estimated_value');

        $winRate = ($wonCount + $lostCount) > 0
            ? round(($wonCount / ($wonCount + $lostCount)) * 100, 1)
            : 0.0;

        // Breakdown by Stage
        $byStage = Opportunity::select('stage', DB::raw('count(*) as count'), DB::raw('sum(estimated_value) as total_value'))
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        // Breakdown by Priority
        $byPriority = Opportunity::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        // Breakdown by Product
        $byProduct = Opportunity::select('product', DB::raw('count(*) as count'), DB::raw('sum(estimated_value) as total_value'))
            ->whereNotNull('product')
            ->groupBy('product')
            ->get();

        return $this->success([
            'total' => $totalOpportunities,
            'open' => $openOpportunities,
            'pipeline_value' => $pipelineValue,
            'formatted_pipeline_value' => $this->formatIndianValue($pipelineValue),
            'won' => $wonCount,
            'won_value' => $wonValue,
            'lost' => $lostCount,
            'lost_value' => $lostValue,
            'closing_this_month' => $closingThisMonth,
            'closing_this_month_value' => $closingThisMonthValue,
            'win_rate' => $winRate,
            'by_stage' => $byStage,
            'by_priority' => $byPriority,
            'by_product' => $byProduct,
        ], 'Opportunity statistics retrieved successfully');
    }

    /**
     * Get reference form options (dropdowns, salespersons, customers, next ID).
     */
    public function formOptions(): JsonResponse
    {
        $nextId = (Opportunity::withTrashed()->max('id') ?? 0) + 1;
        $nextOpportunityId = 'OPP-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $customers = Customer::select(
            'id',
            'customer_id',
            'company_name',
            'contact_person_name',
            'contact_email',
            'contact_phone',
            'billing_address',
            'payment_terms'
        )
            ->orderBy('company_name')
            ->get()
            ->map(function ($cust) {
                return [
                    'id' => $cust->id,
                    'customer_id' => $cust->customer_id,
                    'company_name' => $cust->company_name,
                    'name' => $cust->company_name,
                    'contact_person_name' => $cust->contact_person_name,
                    'contact_email' => $cust->contact_email,
                    'contact_phone' => $cust->contact_phone,
                    'billing_address' => $cust->billing_address,
                    'payment_terms' => $cust->payment_terms,
                ];
            });

        $salespersons = Employee::with('user.designation', 'user.department')
            ->select('id', 'user_id', 'employee_id', 'first_name', 'last_name', 'company_email', 'personal_email')
            ->get()
            ->map(function ($emp) {
                $fullName = trim($emp->first_name . ' ' . ($emp->last_name ?? ''));
                return [
                    'id' => $emp->id,
                    'name' => $fullName ?: 'Employee #' . $emp->id,
                    'employee_id' => $emp->employee_id,
                    'email' => $emp->company_email ?? $emp->personal_email,
                    'designation' => optional(optional($emp->user)->designation)->name,
                    'department' => optional(optional($emp->user)->department)->name,
                ];
            });

        // Fetch stages from database or fallback to defaults
        try {
            $stageRecords = OpportunityStage::orderBy('sort_order')->orderBy('id')->get();
            $stages = $stageRecords->isNotEmpty()
                ? $stageRecords->map(fn($s) => [
                    'id'                  => $s->id,
                    'name'                => $s->name,
                    'color'               => $s->color,
                    'default_probability' => (float) $s->default_probability,
                    'sort_order'          => $s->sort_order,
                ])->toArray()
                : OpportunityStage::DEFAULTS;
        } catch (\Throwable $e) {
            $stages = OpportunityStage::DEFAULTS;
        }

        // Fetch lost reasons from database or fallback to defaults
        try {
            $lostReasonsRecords = OpportunityLostReason::orderBy('sort_order')->orderBy('name')->get();
            $lostReasons = $lostReasonsRecords->isNotEmpty()
                ? $lostReasonsRecords->pluck('name')->toArray()
                : OpportunityLostReason::DEFAULTS;
            $lostReasonsDetail = $lostReasonsRecords->isNotEmpty()
                ? $lostReasonsRecords
                : array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name, 'sort_order' => $i + 1], OpportunityLostReason::DEFAULTS, array_keys(OpportunityLostReason::DEFAULTS));
        } catch (\Throwable $e) {
            $lostReasons = OpportunityLostReason::DEFAULTS;
            $lostReasonsDetail = array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name, 'sort_order' => $i + 1], OpportunityLostReason::DEFAULTS, array_keys(OpportunityLostReason::DEFAULTS));
        }

        // Fetch win reasons from database or fallback to defaults
        try {
            $winReasonsRecords = OpportunityWinReason::orderBy('sort_order')->orderBy('name')->get();
            $winReasons = $winReasonsRecords->isNotEmpty()
                ? $winReasonsRecords->pluck('name')->toArray()
                : OpportunityWinReason::DEFAULTS;
            $winReasonsDetail = $winReasonsRecords->isNotEmpty()
                ? $winReasonsRecords
                : array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name, 'sort_order' => $i + 1], OpportunityWinReason::DEFAULTS, array_keys(OpportunityWinReason::DEFAULTS));
        } catch (\Throwable $e) {
            $winReasons = OpportunityWinReason::DEFAULTS;
            $winReasonsDetail = array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name, 'sort_order' => $i + 1], OpportunityWinReason::DEFAULTS, array_keys(OpportunityWinReason::DEFAULTS));
        }

        // Fetch opportunity types from database or fallback to defaults
        try {
            $typeRecords = OpportunityType::orderBy('sort_order')->orderBy('name')->get();
            $opportunityTypes = $typeRecords->isNotEmpty()
                ? $typeRecords->pluck('name')->toArray()
                : OpportunityType::DEFAULTS;
            $opportunityTypesDetail = $typeRecords->isNotEmpty()
                ? $typeRecords
                : array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name, 'sort_order' => $i + 1], OpportunityType::DEFAULTS, array_keys(OpportunityType::DEFAULTS));
        } catch (\Throwable $e) {
            $opportunityTypes = OpportunityType::DEFAULTS;
            $opportunityTypesDetail = array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name, 'sort_order' => $i + 1], OpportunityType::DEFAULTS, array_keys(OpportunityType::DEFAULTS));
        }

        // Fetch lead sources from database or fallback to defaults
        try {
            $leadSourceRecords = LeadSource::orderBy('id')->get();
            $leadSources = $leadSourceRecords->isNotEmpty()
                ? $leadSourceRecords->pluck('name')->toArray()
                : LeadSource::DEFAULTS;
        } catch (\Throwable $e) {
            $leadSources = [
                'Website',
                'Referral',
                'Cold Call',
                'LinkedIn',
                'Campaign',
                'Social Media',
                'Event',
                'Partner',
                'Existing Customer',
                'Other',
            ];
        }

        // Fetch priorities from database or fallback to defaults
        try {
            $priorityRecords = LeadPriority::orderBy('sort_order')->orderBy('id')->get();
            $priorities = $priorityRecords->isNotEmpty()
                ? $priorityRecords->pluck('name')->toArray()
                : ['Low', 'Medium', 'High'];
            $prioritiesDetail = $priorityRecords->isNotEmpty()
                ? $priorityRecords->toArray()
                : array_map(fn($p) => ['name' => $p['name'], 'color' => $p['color'] ?? '#6B7280'], LeadPriority::DEFAULTS);
        } catch (\Throwable $e) {
            $priorities = ['Low', 'Medium', 'High'];
            $prioritiesDetail = array_map(fn($p) => ['name' => $p['name'], 'color' => $p['color'] ?? '#6B7280'], LeadPriority::DEFAULTS);
        }

        // Fetch products from database
        try {
            $productRecords = Product::where('status', Product::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'type', 'selling_price']);
            $products = $productRecords->isNotEmpty()
                ? $productRecords->pluck('name')->toArray()
                : ['HRMS', 'ERP', 'CRM', 'Payroll', 'Attendance System', 'Custom Development'];
            $productsDetail = $productRecords->isNotEmpty() ? $productRecords : [];
        } catch (\Throwable $e) {
            $products = ['HRMS', 'ERP', 'CRM', 'Payroll', 'Attendance System', 'Custom Development'];
            $productsDetail = [];
        }

        // Fetch currencies from ProductCurrency settings
        try {
            $currencyList = ProductCurrency::orderBy('id')->get(['code', 'symbol', 'name', 'is_default'])->toArray();
            if (empty($currencyList)) {
                $currencyList = ProductCurrency::DEFAULTS;
            }
        } catch (\Throwable $e) {
            $currencyList = array_map(fn($c) => ['code' => $c, 'symbol' => $c, 'name' => $c, 'is_default' => $c === 'INR'], ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SGD', 'CAD', 'AUD']);
        }
        $currencies = array_column($currencyList, 'code');

        $options = [
            'next_opportunity_id' => $nextOpportunityId,
            'customers' => $customers,
            'salespersons' => $salespersons,
            'sales_teams' => [
                'Direct Sales',
                'Enterprise Sales',
                'Inside Sales',
                'Channel Partners',
                'Government & PSU',
                'SMB Sales',
            ],
            'lead_sources' => $leadSources,
            'products' => $products,
            'products_detail' => $productsDetail,
            'stages' => $stages,
            'lost_reasons' => $lostReasons,
            'lost_reasons_detail' => $lostReasonsDetail,
            'win_reasons' => $winReasons,
            'win_reasons_detail' => $winReasonsDetail,
            'opportunity_types' => $opportunityTypes,
            'opportunity_types_detail' => $opportunityTypesDetail,
            'priorities' => $priorities,
            'priorities_detail' => $prioritiesDetail,
            'currencies' => $currencies,
            'currency_details' => $currencyList,
        ];

        return $this->success($options, 'Opportunity form options retrieved successfully');
    }

    /**
     * Store a newly created opportunity.
     */
    public function store(StoreOpportunityRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();

        // Separate action and activity from model attributes
        $action = $validated['action'] ?? null;
        $activity = $validated['activity'] ?? null;
        unset($validated['action'], $validated['activity']);

        // Align single product and products array
        if (!empty($validated['products']) && is_array($validated['products']) && empty($validated['product'])) {
            $validated['product'] = $validated['products'][0] ?? null;
        } elseif (!empty($validated['product']) && empty($validated['products'])) {
            $validated['products'] = [$validated['product']];
        }

        DB::beginTransaction();
        try {
            $opportunity = Opportunity::create($validated);

            // Handle file attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $storedPath = $file->store('opportunities/attachments', 'public');
                    $storedName = basename($storedPath);

                    OpportunityAttachment::create([
                        'opportunity_id' => $opportunity->id,
                        'original_name' => $originalName,
                        'file_name' => $storedName,
                        'file_path' => $storedPath,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getClientMimeType(),
                        'uploaded_by' => Auth::id(),
                    ]);
                }
            }

            // Optional: Handle "Save & Schedule Activity"
            $createdActivity = null;
            if ($action === 'save_and_schedule_activity' || !empty($activity)) {
                $createdActivity = Activity::create([
                    'title' => $activity['title'] ?? ('Follow-up on ' . $opportunity->name),
                    'activity_type' => $activity['activity_type'] ?? 'Task',
                    'priority' => $activity['priority'] ?? $opportunity->priority ?? 'Medium',
                    'related_record_type' => 'Opportunity',
                    'related_record_id' => $opportunity->id,
                    'assigned_employee_id' => Auth::id(),
                    'status' => 'Planned',
                    'start_date_time' => $activity['start_date_time'] ?? now()->addDay(),
                    'end_date_time' => $activity['end_date_time'] ?? now()->addDay()->addHour(),
                    'description' => $activity['description'] ?? ($opportunity->next_action ?: 'Opportunity follow-up'),
                ]);
            }

            // Optional: Handle "Save & Create Quotation"
            $createdQuotation = null;
            if ($action === 'save_and_create_quotation') {
                $year = now()->year;
                $nextQId = (Quotation::withTrashed()->max('id') ?? 0) + 1;
                $quoteNumber = 'Q-' . $year . '-' . str_pad($nextQId, 4, '0', STR_PAD_LEFT);

                $createdQuotation = Quotation::create([
                    'quotation_number' => $quoteNumber,
                    'customer_id' => $opportunity->customer_id,
                    'opportunity_id' => $opportunity->id,
                    'contact_person' => $opportunity->contact_person,
                    'contact_email' => $opportunity->customer?->contact_email,
                    'contact_phone' => $opportunity->customer?->contact_phone,
                    'billing_address' => $opportunity->customer?->billing_address,
                    'quotation_date' => now()->toDateString(),
                    'valid_until' => $opportunity->expected_closing_date ? Carbon::parse($opportunity->expected_closing_date)->toDateString() : now()->addDays(30)->toDateString(),
                    'currency' => $opportunity->currency ?? 'INR',
                    'subtotal' => (float) $opportunity->estimated_value,
                    'grand_total' => (float) $opportunity->estimated_value,
                    'notes' => $opportunity->notes ?? ('Created from Opportunity: ' . $opportunity->name),
                    'status' => 'draft',
                    'created_by' => Auth::id(),
                ]);
            }

            DB::commit();

            $loaded = $opportunity->load(['customer', 'assignedSalesperson', 'attachments', 'quotations', 'activities']);

            $responseData = [
                'opportunity' => $loaded,
            ];
            if ($createdQuotation) {
                $responseData['quotation'] = $createdQuotation;
            }
            if ($createdActivity) {
                $responseData['activity'] = $createdActivity;
            }

            return $this->success(
                $responseData,
                'Opportunity created successfully',
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create opportunity: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified opportunity.
     */
    public function show(Opportunity $opportunity): JsonResponse
    {
        return $this->success(
            $opportunity->load([
                'customer',
                'assignedSalesperson',
                'creator',
                'updater',
                'attachments',
                'quotations.items',
                'activities.assignedEmployee.employee',
                'activities.assignedEmployee.department',
                'activities.assignedEmployee.designation',
            ]),
            'Opportunity details retrieved successfully'
        );
    }

    /**
     * Update the specified opportunity.
     */
    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): JsonResponse
    {
        $validated = $request->validated();
        $validated['updated_by'] = Auth::id();

        if (isset($validated['products']) && is_array($validated['products']) && empty($validated['product'])) {
            $validated['product'] = $validated['products'][0] ?? null;
        } elseif (isset($validated['product']) && empty($validated['products'])) {
            $validated['products'] = [$validated['product']];
        }

        DB::beginTransaction();
        try {
            $opportunity->update($validated);

            // Handle new file attachments if any
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $originalName = $file->getClientOriginalName();
                    $storedPath = $file->store('opportunities/attachments', 'public');
                    $storedName = basename($storedPath);

                    OpportunityAttachment::create([
                        'opportunity_id' => $opportunity->id,
                        'original_name' => $originalName,
                        'file_name' => $storedName,
                        'file_path' => $storedPath,
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getClientMimeType(),
                        'uploaded_by' => Auth::id(),
                    ]);
                }
            }

            DB::commit();

            return $this->success(
                $opportunity->load(['customer', 'assignedSalesperson', 'attachments', 'quotations', 'activities']),
                'Opportunity updated successfully'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to update opportunity: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Quick stage and probability update (e.g. dragging across Kanban board).
     */
    public function updateStage(Request $request, Opportunity $opportunity): JsonResponse
    {
        $validated = $request->validate([
            'stage' => 'required|string|max:50',
            'probability' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $stage = $validated['stage'];
        $opportunity->stage = $stage;

        if (isset($validated['probability'])) {
            $opportunity->probability = $validated['probability'];
        } else {
            // Apply default stage probability
            $defaultProbs = [
                'New' => 20,
                'Qualified' => 40,
                'Proposal Sent' => 60,
                'Negotiation' => 70,
                'Won' => 100,
                'Lost' => 0,
            ];
            foreach ($defaultProbs as $s => $prob) {
                if (strcasecmp(trim($stage), trim($s)) === 0) {
                    $opportunity->probability = $prob;
                    break;
                }
            }
        }

        if (!empty($validated['notes'])) {
            $opportunity->notes = ($opportunity->notes ? $opportunity->notes . "\n" : '')
                . "[" . now()->format('Y-m-d H:i') . "] Stage updated to {$stage}: " . $validated['notes'];
        }

        $opportunity->updated_by = Auth::id();
        $opportunity->save();

        return $this->success(
            $opportunity->load(['customer', 'assignedSalesperson']),
            'Opportunity stage updated successfully'
        );
    }

    /**
     * Remove the specified opportunity (soft delete).
     */
    public function destroy(Opportunity $opportunity): JsonResponse
    {
        $opportunity->deleted_by = Auth::id();
        $opportunity->save();
        $opportunity->delete();

        return $this->success(null, 'Opportunity deleted successfully');
    }

    // ─── Attachments Handling ──────────────────────────────────────────────────

    /**
     * Upload additional attachments to an opportunity.
     */
    public function uploadAttachments(Request $request, Opportunity $opportunity): JsonResponse
    {
        $request->validate([
            'attachments' => 'required|array|min:1',
            'attachments.*' => 'file|max:20480',
        ]);

        $uploaded = [];
        foreach ($request->file('attachments') as $file) {
            $originalName = $file->getClientOriginalName();
            $storedPath = $file->store('opportunities/attachments', 'public');
            $storedName = basename($storedPath);

            $attachment = OpportunityAttachment::create([
                'opportunity_id' => $opportunity->id,
                'original_name' => $originalName,
                'file_name' => $storedName,
                'file_path' => $storedPath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
                'uploaded_by' => Auth::id(),
            ]);

            $uploaded[] = $attachment;
        }

        return $this->success($uploaded, 'Attachments uploaded successfully', 201);
    }

    /**
     * Delete an attachment.
     */
    public function deleteAttachment(Opportunity $opportunity, OpportunityAttachment $attachment): JsonResponse
    {
        if ($attachment->opportunity_id !== $opportunity->id) {
            return $this->error('Attachment does not belong to this opportunity', 404);
        }

        if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return $this->success(null, 'Attachment deleted successfully');
    }

    /**
     * Download attachment file.
     */
    public function downloadAttachment(Request $request, Opportunity $opportunity, OpportunityAttachment $attachment)
    {
        $this->authenticateFromToken($request);

        if ($attachment->opportunity_id !== $opportunity->id) {
            abort(404, 'Attachment not found for this opportunity.');
        }

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            abort(404, 'File not found on server.');
        }

        return Storage::disk('public')->download($attachment->file_path, $attachment->original_name);
    }

    // ─── Quick Actions (Buttons from Add Opportunity) ─────────────────────────

    /**
     * Action: Save & Create Quotation for this opportunity.
     */
    public function createQuotation(Request $request, Opportunity $opportunity): JsonResponse
    {
        $validated = $request->validate([
            'valid_until' => 'nullable|date',
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'items' => 'nullable|array',
        ]);

        $year = now()->year;
        $nextId = (Quotation::withTrashed()->max('id') ?? 0) + 1;
        $quoteNumber = 'Q-' . $year . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $quotation = Quotation::create([
            'quotation_number' => $quoteNumber,
            'customer_id' => $opportunity->customer_id,
            'opportunity_id' => $opportunity->id,
            'contact_person' => $opportunity->contact_person,
            'contact_email' => $opportunity->customer?->contact_email,
            'contact_phone' => $opportunity->customer?->contact_phone,
            'billing_address' => $opportunity->customer?->billing_address,
            'quotation_date' => now()->toDateString(),
            'valid_until' => $validated['valid_until'] ?? ($opportunity->expected_closing_date ? Carbon::parse($opportunity->expected_closing_date)->toDateString() : now()->addDays(30)->toDateString()),
            'currency' => $opportunity->currency ?? 'INR',
            'payment_terms' => $validated['payment_terms'] ?? $opportunity->customer?->payment_terms ?? 'Net 30',
            'subtotal' => (float) $opportunity->estimated_value,
            'grand_total' => (float) $opportunity->estimated_value,
            'notes' => $validated['notes'] ?? $opportunity->notes ?? ('Quotation created for ' . $opportunity->name),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        return $this->success(
            $quotation->load(['customer', 'opportunity']),
            'Draft quotation generated for opportunity successfully',
            201
        );
    }

    /**
     * Action: Save & Schedule Activity for this opportunity.
     */
    public function scheduleActivity(Request $request, Opportunity $opportunity): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'activity_type' => 'nullable|string|max:50',
            'priority' => 'nullable|string|max:50',
            'start_date_time' => 'required|date',
            'end_date_time' => 'nullable|date|after_or_equal:start_date_time',
            'assigned_employee_id' => 'nullable|integer',
            'reminder' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        $activity = Activity::create([
            'title' => $validated['title'],
            'activity_type' => $validated['activity_type'] ?? 'Task',
            'priority' => $validated['priority'] ?? $opportunity->priority ?? 'Medium',
            'related_record_type' => 'Opportunity',
            'related_record_id' => $opportunity->id,
            'assigned_employee_id' => $validated['assigned_employee_id'] ?? Auth::id(),
            'status' => 'Planned',
            'start_date_time' => $validated['start_date_time'],
            'end_date_time' => $validated['end_date_time'] ?? null,
            'reminder' => $validated['reminder'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return $this->success(
            $activity->load('assignedEmployee'),
            'Activity scheduled for opportunity successfully',
            201
        );
    }

    // ─── Export Endpoints (Excel, CSV, PDF) ───────────────────────────────────

    /**
     * Unified Export Endpoint (supports format=xlsx, format=csv, format=pdf).
     */
    public function export(Request $request)
    {
        $format = strtolower($request->input('format', 'xlsx'));
        if ($format === 'pdf') {
            return $this->exportPdf($request);
        }
        if ($format === 'csv') {
            return $this->exportCsv($request);
        }
        return $this->exportExcel($request);
    }

    /**
     * Export opportunities to Excel (.xlsx).
     */
    public function exportExcel(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $opportunities = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($opportunities);
        $filename = 'crm_opportunities_' . now()->format('YmdHis') . '.xlsx';

        return Excel::download(new OpportunityExport($rows), $filename, \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export opportunities to CSV (.csv).
     */
    public function exportCsv(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $opportunities = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($opportunities);
        $filename = 'crm_opportunities_' . now()->format('YmdHis') . '.csv';

        return Excel::download(new OpportunityExport($rows), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * Export opportunities to PDF (.pdf).
     */
    public function exportPdf(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $opportunities = $this->buildFilteredQuery($request)->get();
        $company = Company::first();

        // Calculate stats for the PDF header summary
        $totalOpportunities = $opportunities->count();
        $openOpportunities = $opportunities->whereNotIn('stage', ['Won', 'Lost'])->count();
        $pipelineValue = (float) $opportunities->whereNotIn('stage', ['Won', 'Lost'])->sum('estimated_value');
        $wonCount = $opportunities->where('stage', 'Won')->count();
        $lostCount = $opportunities->where('stage', 'Lost')->count();

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();
        $closingThisMonth = $opportunities->filter(function ($item) use ($startOfMonth, $endOfMonth) {
            return $item->expected_closing_date && $item->expected_closing_date >= $startOfMonth && $item->expected_closing_date <= $endOfMonth;
        })->count();

        $stats = [
            'total' => $totalOpportunities,
            'open' => $openOpportunities,
            'formatted_pipeline_value' => $this->formatIndianValue($pipelineValue),
            'won' => $wonCount,
            'lost' => $lostCount,
            'closing_this_month' => $closingThisMonth,
        ];

        $pdf = Pdf::loadView('reports.opportunity_pdf', [
            'opportunities' => $opportunities,
            'company' => $company,
            'stats' => $stats,
            'title' => 'CRM Opportunities Pipeline Report',
        ])->setPaper('a4', 'landscape');

        $filename = 'crm_opportunities_' . now()->format('YmdHis') . '.pdf';
        return $pdf->download($filename);
    }

    // ─── Private Helpers ────────────────────────────────────────────────────────

    /**
     * Build the filtered query (shared between index, pipeline, and exports).
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Opportunity::with(['customer', 'assignedSalesperson', 'creator', 'attachments']);

        // Search across Name, OPP ID, Description, Competitor, Decision Maker, or Customer Company Name
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('opportunity_id', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('product', 'like', "%{$search}%")
                    ->orWhere('competitor', 'like', "%{$search}%")
                    ->orWhere('decision_maker', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('company_name', 'like', "%{$search}%")
                            ->orWhere('contact_person_name', 'like', "%{$search}%");
                    });
            });
        }

        // Customer Filter
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Stage Filter (string or array)
        if ($request->filled('stage')) {
            if (is_array($request->stage)) {
                $query->whereIn('stage', $request->stage);
            } else {
                $query->where('stage', $request->stage);
            }
        }

        // Salesperson Filter
        if ($request->filled('assigned_salesperson_id')) {
            $query->where('assigned_salesperson_id', $request->assigned_salesperson_id);
        }

        // Priority Filter
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Lead Source Filter
        if ($request->filled('lead_source')) {
            $query->where('lead_source', $request->lead_source);
        }

        // Product Filter
        if ($request->filled('product')) {
            $prod = $request->product;
            $query->where(function ($q) use ($prod) {
                $q->where('product', $prod)
                    ->orWhereJsonContains('products', $prod);
            });
        }

        // Currency Filter
        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        // Closing Month Filter (e.g. ?closing_month=2026-10 or 'current')
        if ($request->filled('closing_month')) {
            if ($request->closing_month === 'current') {
                $query->whereMonth('expected_closing_date', now()->month)
                    ->whereYear('expected_closing_date', now()->year);
            } else {
                try {
                    $monthCarbon = Carbon::parse($request->closing_month);
                    $query->whereMonth('expected_closing_date', $monthCarbon->month)
                        ->whereYear('expected_closing_date', $monthCarbon->year);
                } catch (\Throwable $e) {
                    // Fallback
                }
            }
        }

        // Expected Closing Date Range
        if ($request->filled('closing_date_from')) {
            $query->whereDate('expected_closing_date', '>=', $request->closing_date_from);
        }
        if ($request->filled('closing_date_to')) {
            $query->whereDate('expected_closing_date', '<=', $request->closing_date_to);
        }

        // Created At Date Range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = [
            'created_at',
            'name',
            'opportunity_id',
            'estimated_value',
            'stage',
            'probability',
            'expected_closing_date',
            'priority',
        ];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortOrder) === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        return $query;
    }

    /**
     * Prepare flat rows for Excel / CSV export.
     */
    private function prepareExportRows($opportunities): array
    {
        $rows = [];
        foreach ($opportunities as $opp) {
            $rows[] = [
                $opp->opportunity_id ?? ('OPP-' . str_pad($opp->id, 4, '0', STR_PAD_LEFT)),
                $opp->name ?? '-',
                $opp->customer_name ?? '-',
                $opp->contact_person ?? '-',
                $opp->product_display ?? '-',
                number_format((float) ($opp->estimated_value ?? 0), 2),
                $opp->currency ?? 'INR',
                $opp->stage ?? 'New',
                $opp->probability . '%',
                $opp->expected_closing_date ? Carbon::parse($opp->expected_closing_date)->format('Y-m-d') : '-',
                $opp->assigned_salesperson_name ?? 'Unassigned',
                $opp->sales_team ?? '-',
                $opp->priority ?? 'Medium',
                $opp->lead_source ?? '-',
                $opp->competitor ?? '-',
                $opp->decision_maker ?? '-',
                $opp->customer_requirements ?? '-',
                $opp->next_action ?? '-',
                $opp->notes ?? '-',
                $opp->created_at ? $opp->created_at->format('Y-m-d H:i') : '-',
            ];
        }
        return $rows;
    }

    /**
     * Helper to format value in Indian format (₹2.00L, ₹80K, ₹19.50L).
     */
    private function formatIndianValue(float $val): string
    {
        if ($val >= 10000000) {
            return '₹' . number_format($val / 10000000, 2) . 'Cr';
        }
        if ($val >= 100000) {
            return '₹' . number_format($val / 100000, 2) . 'L';
        }
        if ($val >= 1000) {
            return '₹' . round($val / 1000) . 'K';
        }
        return '₹' . number_format($val, 2);
    }

    /**
     * Authenticate via Bearer token or URL query parameter token (for direct browser file downloads).
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
            abort(403, 'Forbidden - Access denied. Please provide a valid authentication token.');
        }
    }
}
