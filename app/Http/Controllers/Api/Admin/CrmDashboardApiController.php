<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\LeadFollowUp;
use App\Models\Opportunity;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmDashboardApiController extends ApiController
{
    /**
     * Get CRM Dashboard Data including KPI cards, Pipeline Chart, Lead Sources Donut,
     * Monthly Sales Spline Chart, Upcoming Activities Table, and Recent Activity Feed.
     *
     * GET /api/admin/crm/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        // Filter queries
        $leadQuery = $this->applyFilters(Lead::query(), $request);
        $opportunityQuery = $this->applyFilters(Opportunity::query(), $request);
        $quotationQuery = $this->applyQuotationFilters(Quotation::query(), $request);
        $customerQuery = Customer::query();

        // ──────────────────────────────────────────────────────────────────────
        // 1. KPI CARDS (Row 1, 2, 3 as seen in UI)
        // ──────────────────────────────────────────────────────────────────────

        // Row 1:
        // - Total leads
        $totalLeads = (clone $leadQuery)->count();

        // - Total customers
        $totalCustomers = (clone $customerQuery)->count();

        // - Open opportunities (not Won/Lost)
        $openOpportunitiesCount = (clone $opportunityQuery)
            ->whereNotIn('stage', ['Won', 'Lost'])
            ->count();

        // - Estimated open deal value
        $estimatedOpenDealValue = (float) (clone $opportunityQuery)
            ->whereNotIn('stage', ['Won', 'Lost'])
            ->sum('estimated_value');

        // Row 2:
        // - New leads this month (or in selected period)
        $newLeadsThisMonth = (clone $leadQuery)
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString())
            ->count();

        // - Won deals this month
        $wonDealsThisMonth = (clone $opportunityQuery)
            ->where('stage', 'Won')
            ->whereDate('updated_at', '>=', $startDate->toDateString())
            ->whereDate('updated_at', '<=', $endDate->toDateString())
            ->count();

        // - Lost deals this month
        $lostDealsThisMonth = (clone $opportunityQuery)
            ->where('stage', 'Lost')
            ->whereDate('updated_at', '>=', $startDate->toDateString())
            ->whereDate('updated_at', '<=', $endDate->toDateString())
            ->count();

        // - Follow-ups due today (from LeadFollowUp scheduled for today)
        $todayStr = Carbon::today()->toDateString();
        $followUpsDueToday = LeadFollowUp::query()
            ->where('status', 'scheduled')
            ->whereDate('follow_up_date', $todayStr)
            ->count();

        // Row 3:
        // - Overdue activities (start_date_time < now and status != completed/cancelled)
        $overdueActivitiesCount = Activity::query()
            ->where('start_date_time', '<', Carbon::now())
            ->whereNotIn('status', ['completed', 'Completed', 'cancelled', 'Cancelled'])
            ->count();

        // - Conversion rate % (Won / (Won + Lost) or Won / Total closed)
        $totalClosed = $wonDealsThisMonth + $lostDealsThisMonth;
        if ($totalClosed > 0) {
            $conversionRate = round(($wonDealsThisMonth / $totalClosed) * 100, 1);
        } else {
            // Fallback to all-time conversion rate
            $allWon = (clone $opportunityQuery)->where('stage', 'Won')->count();
            $allLost = (clone $opportunityQuery)->where('stage', 'Lost')->count();
            $allClosed = $allWon + $allLost;
            $conversionRate = $allClosed > 0 ? round(($allWon / $allClosed) * 100, 1) : 0.0;
        }

        // - Quotation value (sum of quotations grand_total in period or total open)
        $quotationValue = (float) (clone $quotationQuery)
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString())
            ->sum('grand_total');

        if ($quotationValue <= 0) {
            $quotationValue = (float) (clone $quotationQuery)->sum('grand_total');
        }

        // - Average deal value
        $avgDealValue = (clone $opportunityQuery)->where('stage', 'Won')->avg('estimated_value');
        if (!$avgDealValue) {
            $avgDealValue = (clone $opportunityQuery)->avg('estimated_value') ?? 0;
        }
        $avgDealValue = (float) $avgDealValue;

        $kpis = [
            'total_leads' => [
                'value' => $totalLeads,
                'label' => 'Total leads',
            ],
            'total_customers' => [
                'value' => $totalCustomers,
                'label' => 'Total customers',
            ],
            'open_opportunities' => [
                'value' => $openOpportunitiesCount,
                'label' => 'Open opportunities',
            ],
            'estimated_open_deal_value' => [
                'value' => $estimatedOpenDealValue,
                'formatted' => $this->formatIndianValue($estimatedOpenDealValue),
                'label' => 'Estimated open deal value',
            ],
            'new_leads_this_month' => [
                'value' => $newLeadsThisMonth,
                'label' => 'New leads this month',
            ],
            'won_deals_this_month' => [
                'value' => $wonDealsThisMonth,
                'label' => 'Won deals this month',
            ],
            'lost_deals_this_month' => [
                'value' => $lostDealsThisMonth,
                'label' => 'Lost deals this month',
            ],
            'follow_ups_due_today' => [
                'value' => $followUpsDueToday,
                'label' => 'Follow-ups due today',
            ],
            'overdue_activities' => [
                'value' => $overdueActivitiesCount,
                'label' => 'Overdue activities',
            ],
            'conversion_rate' => [
                'value' => $conversionRate,
                'formatted' => $conversionRate . '%',
                'label' => 'Conversion rate',
            ],
            'quotation_value' => [
                'value' => $quotationValue,
                'formatted' => $this->formatIndianValue($quotationValue),
                'label' => 'Quotation value',
            ],
            'average_deal_value' => [
                'value' => $avgDealValue,
                'formatted' => $this->formatIndianValue($avgDealValue),
                'label' => 'Average deal value',
            ],
        ];

        // ──────────────────────────────────────────────────────────────────────
        // 2. SALES PIPELINE (Bar Chart)
        // ──────────────────────────────────────────────────────────────────────
        $pipelineStages = ['New', 'Qualified', 'Proposal Sent', 'Negotiation', 'Won', 'Lost'];
        $stageColors = [
            'New'           => '#3B82F6',
            'Qualified'     => '#3B82F6',
            'Proposal Sent' => '#3B82F6',
            'Negotiation'   => '#3B82F6',
            'Won'           => '#3B82F6',
            'Lost'          => '#3B82F6',
        ];

        $oppsForPipeline = (clone $opportunityQuery)->get();
        $salesPipeline = [];
        foreach ($pipelineStages as $stage) {
            $matched = $oppsForPipeline->filter(fn($o) => strcasecmp(trim($o->stage), trim($stage)) === 0);
            $stageCount = $matched->count();
            $stageValue = (float) $matched->sum('estimated_value');

            $salesPipeline[] = [
                'stage'           => $stage,
                'count'           => $stageCount,
                'value'           => $stageValue,
                'formatted_value' => $this->formatIndianValue($stageValue),
                'color'           => $stageColors[$stage] ?? '#3B82F6',
            ];
        }

        // ──────────────────────────────────────────────────────────────────────
        // 3. LEAD SOURCES (Donut Chart)
        // ──────────────────────────────────────────────────────────────────────
        $sourceColors = [
            'Website'        => '#3B82F6',
            'Referral'       => '#10B981',
            'Social Media'   => '#F59E0B',
            'Phone'          => '#8B5CF6',
            'Email'          => '#EC4899',
            'Exhibition'     => '#06B6D4',
            'Manual Entry'   => '#EF4444',
            'Other'          => '#6B7280',
        ];

        $allLeads = (clone $leadQuery)->get();
        $leadsCountTotal = $allLeads->count();
        $sourcesGroup = $allLeads->groupBy(function ($l) {
            return !empty($l->lead_source) ? trim($l->lead_source) : 'Other';
        });

        $leadSources = [];
        foreach ($sourcesGroup as $src => $items) {
            $count = $items->count();
            $percentage = $leadsCountTotal > 0 ? round(($count / $leadsCountTotal) * 100) : 0;
            $leadSources[] = [
                'source'     => $src,
                'count'      => $count,
                'percentage' => (int) $percentage,
                'color'      => $sourceColors[$src] ?? '#94A3B8',
            ];
        }

        // Sort descending by count
        usort($leadSources, fn($a, $b) => $b['count'] <=> $a['count']);

        // ──────────────────────────────────────────────────────────────────────
        // 4. MONTHLY SALES (Deals Count & Value Trend Chart)
        // ──────────────────────────────────────────────────────────────────────
        $monthlySales = $this->buildMonthlySalesChart($request);

        // ──────────────────────────────────────────────────────────────────────
        // 5. UPCOMING ACTIVITIES TABLE
        // ──────────────────────────────────────────────────────────────────────
        $upcomingActivities = $this->getUpcomingActivities($request);

        // ──────────────────────────────────────────────────────────────────────
        // 6. RECENT ACTIVITY FEED
        // ──────────────────────────────────────────────────────────────────────
        $recentActivityFeed = $this->getRecentActivityFeed($request);

        // ──────────────────────────────────────────────────────────────────────
        // 7. FILTER OPTIONS
        // ──────────────────────────────────────────────────────────────────────
        $salespersons = Employee::select('id', 'user_id', 'employee_id', 'first_name', 'last_name')
            ->get()
            ->map(function ($emp) {
                return [
                    'id'   => $emp->id,
                    'name' => trim($emp->first_name . ' ' . ($emp->last_name ?? '')),
                ];
            });

        $existingTeams = Opportunity::whereNotNull('sales_team')
            ->where('sales_team', '!=', '')
            ->distinct()
            ->pluck('sales_team')
            ->toArray();
        $salesTeams = array_values(array_unique(array_merge(['All Teams', 'Direct Sales', 'Enterprise Team', 'Inside Sales', 'Channel Partners'], $existingTeams)));

        $filters = [
            'date_ranges' => [
                'today'        => 'Today',
                'this_week'    => 'This Week',
                'this_month'   => 'This Month',
                'last_month'   => 'Last Month',
                'this_quarter' => 'This Quarter',
                'this_year'    => 'This Year',
            ],
            'salespersons' => $salespersons,
            'sales_teams'  => $salesTeams,
        ];

        return $this->success([
            'kpis'                => $kpis,
            'sales_pipeline'      => $salesPipeline,
            'lead_sources'        => $leadSources,
            'monthly_sales'       => $monthlySales,
            'upcoming_activities' => $upcomingActivities,
            'recent_activities'   => $recentActivityFeed,
            'filters'             => $filters,
        ], 'CRM Dashboard data retrieved successfully');
    }

    /**
     * Build 9-month historical sales data (Jan - Sep or trailing 9 months).
     */
    private function buildMonthlySalesChart(Request $request): array
    {
        $months = [];
        $currentDate = Carbon::now();

        // 9 months series as seen in the UI (Jan to Sep)
        for ($i = 8; $i >= 0; $i--) {
            $monthAnchor = (clone $currentDate)->subMonths($i);
            $start = (clone $monthAnchor)->startOfMonth();
            $end = (clone $monthAnchor)->endOfMonth();

            $oppQuery = Opportunity::query()
                ->whereDate('created_at', '>=', $start->toDateString())
                ->whereDate('created_at', '<=', $end->toDateString());

            if ($request->filled('salesperson_id')) {
                $oppQuery->where('assigned_salesperson_id', $request->salesperson_id);
            }
            if ($request->filled('team') && $request->team !== 'All Teams') {
                $oppQuery->where('sales_team', $request->team);
            }

            $opps = $oppQuery->get();
            $dealsCount = $opps->count();
            $dealsValue = (float) $opps->sum('estimated_value');

            $months[] = [
                'month'           => $start->format('M'),
                'full_month'      => $start->format('F Y'),
                'deals'           => $dealsCount,
                'value'           => $dealsValue,
                'formatted_value' => $this->formatIndianValue($dealsValue),
            ];
        }

        return [
            'labels'           => array_column($months, 'month'),
            'deals'            => array_column($months, 'deals'),
            'values'           => array_column($months, 'value'),
            'formatted_values' => array_column($months, 'formatted_value'),
            'series'           => $months,
        ];
    }

    /**
     * Upcoming activities table (Activity, Customer, Assignee, Due, Priority, Status, Action).
     */
    private function getUpcomingActivities(Request $request): array
    {
        $query = Activity::with(['assignedEmployee'])
            ->whereDate('start_date_time', '>=', Carbon::today()->toDateString())
            ->orderBy('start_date_time', 'asc')
            ->take(10);

        if ($request->filled('salesperson_id')) {
            $query->where('assigned_employee_id', $request->salesperson_id);
        }

        $activities = $query->get();

        $rows = [];
        foreach ($activities as $act) {
            $assigneeName = 'Unassigned';
            if ($act->assignedEmployee) {
                $assigneeName = trim(($act->assignedEmployee->first_name ?? '') . ' ' . ($act->assignedEmployee->last_name ?? ''));
                if (!$assigneeName) {
                    $assigneeName = $act->assignedEmployee->username ?? 'Employee #' . $act->assigned_employee_id;
                }
            }

            $customerName = 'N/A';
            if ($act->related_record_type === 'Customer' && $act->related_record_id) {
                $cust = Customer::find($act->related_record_id);
                $customerName = $cust?->company_name ?? $cust?->contact_person_name ?? 'N/A';
            } elseif ($act->related_record_type === 'Opportunity' && $act->related_record_id) {
                $opp = Opportunity::with('customer')->find($act->related_record_id);
                $customerName = $opp?->customer?->company_name ?? $opp?->contact_person ?? 'N/A';
            } elseif ($act->related_record_type === 'Lead' && $act->related_record_id) {
                $lead = Lead::find($act->related_record_id);
                $customerName = $lead?->company_name ?? $lead?->lead_name ?? 'N/A';
            }

            $dueFormatted = $act->start_date_time ? Carbon::parse($act->start_date_time)->format('d M') : '-';
            $priority = ucfirst(strtolower($act->priority ?? 'Medium'));
            $status = ucfirst(strtolower($act->status ?? 'Pending'));

            $priorityBadge = match (strtolower($priority)) {
                'high', 'urgent' => ['color' => '#DC2626', 'bg' => '#FEE2E2'],
                'low'            => ['color' => '#059669', 'bg' => '#ECFDF5'],
                default          => ['color' => '#D97706', 'bg' => '#FEF3C7'],
            };

            $rows[] = [
                'id'             => $act->id,
                'activity'       => $act->title ?? $act->activity_type ?? 'Activity',
                'activity_type'  => $act->activity_type ?? 'Call',
                'customer'       => $customerName,
                'assignee'       => $assigneeName,
                'due'            => $dueFormatted,
                'raw_due'        => $act->start_date_time,
                'priority'       => $priority,
                'priority_badge' => $priorityBadge,
                'status'         => $status,
                'action_view_id' => $act->id,
            ];
        }

        return $rows;
    }

    /**
     * Recent activity feed (New lead created, Customer updated, Opportunity moved to stage, Quotation created, Follow-up completed).
     */
    private function getRecentActivityFeed(Request $request): array
    {
        $feed = [];

        // 1. Recent Leads
        $leads = Lead::with('assignedSalesperson')->latest()->take(3)->get();
        foreach ($leads as $l) {
            $userOrSp = $l->assignedSalesperson ? trim($l->assignedSalesperson->first_name . ' ' . $l->assignedSalesperson->last_name) : 'Sales Team';
            $feed[] = [
                'id'          => 'lead_' . $l->id,
                'type'        => 'lead_created',
                'title'       => 'New lead created: ' . ($l->company_name ?? $l->lead_name ?? 'New Lead'),
                'subtitle'    => $userOrSp . ' · ' . $l->created_at->diffForHumans(),
                'user'        => $userOrSp,
                'icon'        => 'user-plus',
                'color'       => '#3B82F6',
                'created_at'  => $l->created_at,
            ];
        }

        // 2. Recent Opportunities
        $opps = Opportunity::latest()->take(3)->get();
        foreach ($opps as $o) {
            $userOrSp = $o->assigned_salesperson_name ?? 'Sales Team';
            $feed[] = [
                'id'          => 'opp_' . $o->id,
                'type'        => 'opportunity_updated',
                'title'       => 'Opportunity moved to ' . ($o->stage ?? 'Negotiation') . ': ' . ($o->name ?? 'Deal'),
                'subtitle'    => $userOrSp . ' · ' . $o->updated_at->diffForHumans(),
                'user'        => $userOrSp,
                'icon'        => 'briefcase',
                'color'       => '#6366F1',
                'created_at'  => $o->updated_at,
            ];
        }

        // 3. Recent Customers
        $customers = Customer::latest('updated_at')->take(2)->get();
        foreach ($customers as $c) {
            $feed[] = [
                'id'          => 'cust_' . $c->id,
                'type'        => 'customer_updated',
                'title'       => 'Customer updated: ' . ($c->company_name ?? 'Customer'),
                'subtitle'    => 'System · ' . $c->updated_at->diffForHumans(),
                'user'        => 'System',
                'icon'        => 'user-check',
                'color'       => '#10B981',
                'created_at'  => $c->updated_at,
            ];
        }

        // 4. Recent Quotations
        $quotations = Quotation::latest()->take(2)->get();
        foreach ($quotations as $q) {
            $feed[] = [
                'id'          => 'quote_' . $q->id,
                'type'        => 'quotation_created',
                'title'       => 'Quotation created #' . ($q->quotation_number ?? $q->id),
                'subtitle'    => 'Sales Team · ' . $q->created_at->diffForHumans(),
                'user'        => 'Sales Team',
                'icon'        => 'file-text',
                'color'       => '#F59E0B',
                'created_at'  => $q->created_at,
            ];
        }

        // 5. Completed follow-ups
        $followUps = LeadFollowUp::with(['lead', 'performer'])
            ->where('status', 'completed')
            ->latest('updated_at')
            ->take(2)
            ->get();
        foreach ($followUps as $fu) {
            $sp = $fu->performer ? trim($fu->performer->first_name . ' ' . $fu->performer->last_name) : 'Sales Team';
            $leadName = $fu->lead?->company_name ?? $fu->lead?->lead_name ?? 'Lead';
            $feed[] = [
                'id'          => 'fu_' . $fu->id,
                'type'        => 'followup_completed',
                'title'       => 'Follow-up completed: ' . $leadName,
                'subtitle'    => $sp . ' · ' . $fu->updated_at->diffForHumans(),
                'user'        => $sp,
                'icon'        => 'check-circle',
                'color'       => '#10B981',
                'created_at'  => $fu->updated_at,
            ];
        }

        // Sort by created_at descending and take top 8
        usort($feed, function ($a, $b) {
            return Carbon::parse($b['created_at'])->timestamp <=> Carbon::parse($a['created_at'])->timestamp;
        });

        return array_slice($feed, 0, 8);
    }

    /**
     * Resolve date range filter.
     */
    private function resolveDateRange(Request $request): array
    {
        $now = Carbon::now();
        $dateRange = strtolower($request->input('date_range', 'this_month'));

        if ($request->filled('date_from') && $request->filled('date_to')) {
            return [
                Carbon::parse($request->date_from)->startOfDay(),
                Carbon::parse($request->date_to)->endOfDay(),
            ];
        }

        switch ($dateRange) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

            case 'this_week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];

            case 'last_month':
                return [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()];

            case 'this_quarter':
                return [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()];

            case 'this_year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];

            case 'this_month':
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }
    }

    /**
     * Apply salesperson & team filters to Eloquent query.
     */
    private function applyFilters($query, Request $request)
    {
        if ($request->filled('salesperson_id')) {
            $query->where('assigned_salesperson_id', $request->salesperson_id);
        }

        if ($request->filled('team') && $request->team !== 'All Teams') {
            $query->where('sales_team', $request->team);
        }

        return $query;
    }

    /**
     * Apply filters to Quotation model.
     */
    private function applyQuotationFilters($query, Request $request)
    {
        if ($request->filled('salesperson_id')) {
            $query->whereHas('opportunity', function ($q) use ($request) {
                $q->where('assigned_salesperson_id', $request->salesperson_id);
            });
        }

        return $query;
    }

    /**
     * Format currency value nicely in Indian style (₹12.5L, ₹52K, ₹1.2Cr).
     */
    private function formatIndianValue(float $val): string
    {
        if ($val >= 10000000) {
            return '₹' . number_format($val / 10000000, 2) . 'Cr';
        }
        if ($val >= 100000) {
            return '₹' . number_format($val / 100000, 1) . 'L';
        }
        if ($val >= 1000) {
            return '₹' . round($val / 1000) . 'K';
        }
        return '₹' . number_format($val, 0);
    }
}
