<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\CrmReportExport;
use App\Http\Controllers\Api\ApiController;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CrmReportApiController extends ApiController
{
    /**
     * Get global report filter options (Date range presets, Salespersons, Teams, Customers, Lead Sources, Stages).
     * GET /api/admin/crm/reports/filter-options
     */
    public function filterOptions(): JsonResponse
    {
        $salespersons = Employee::with('user.designation', 'user.department')
            ->select('id', 'user_id', 'employee_id', 'first_name', 'last_name', 'company_email')
            ->get()
            ->map(function ($emp) {
                return [
                    'id'          => $emp->id,
                    'user_id'     => $emp->user_id,
                    'name'        => trim($emp->first_name . ' ' . ($emp->last_name ?? '')),
                    'employee_id' => $emp->employee_id,
                ];
            });

        $teams = Opportunity::query()
            ->whereNotNull('sales_team')
            ->where('sales_team', '!=', '')
            ->distinct()
            ->pluck('sales_team')
            ->toArray();

        if (empty($teams)) {
            $teams = ['Direct Sales', 'Enterprise Team', 'Inside Sales', 'Channel Partners'];
        }

        $customers = Customer::query()
            ->select('id', 'company_name', 'customer_id')
            ->orderBy('company_name')
            ->get()
            ->map(function ($c) {
                return [
                    'id'   => $c->id,
                    'name' => $c->company_name,
                    'code' => $c->customer_id,
                ];
            });

        $leadSources = [
            'Website',
            'Referral',
            'Cold Call',
            'LinkedIn',
            'Email Campaign',
            'Trade Show / Event',
            'Google Ads',
            'Inbound Call',
            'Partner',
            'Other',
        ];

        $stages = [
            'New',
            'Qualified',
            'Proposal Sent',
            'Negotiation',
            'Won',
            'Lost',
        ];

        $dateRanges = [
            ['key' => 'today',         'label' => 'Today'],
            ['key' => 'yesterday',     'label' => 'Yesterday'],
            ['key' => 'this_week',     'label' => 'This Week'],
            ['key' => 'last_week',     'label' => 'Last Week'],
            ['key' => 'this_month',    'label' => 'This Month'],
            ['key' => 'last_month',    'label' => 'Last Month'],
            ['key' => 'this_quarter',  'label' => 'This Quarter'],
            ['key' => 'this_year',     'label' => 'This Year'],
            ['key' => 'last_year',     'label' => 'Last Year'],
            ['key' => 'custom',        'label' => 'Custom Range'],
        ];

        return $this->success([
            'date_ranges'   => $dateRanges,
            'salespersons'  => $salespersons,
            'teams'         => $teams,
            'customers'     => $customers,
            'lead_sources'  => $leadSources,
            'stages'        => $stages,
        ], 'Report filter options retrieved successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. SALES REPORT (Screenshot 1)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Sales Report data: Top KPI cards, Sales Trend (Won vs Lost vs Value by month), Monthly Summary table.
     * GET /api/admin/crm/reports/sales
     */
    public function salesReport(Request $request): JsonResponse
    {
        $data = $this->buildSalesReportData($request);
        return $this->success($data, 'Sales report retrieved successfully');
    }

    /**
     * Export Sales Report (Excel, CSV, PDF).
     * GET /api/admin/crm/reports/sales/export
     */
    public function exportSalesReport(Request $request)
    {
        $this->authenticateFromToken($request);
        $data = $this->buildSalesReportData($request);
        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'sales_report_' . now()->format('YmdHis');

        $headings = ['Month', 'Deals Won', 'Deals Lost', 'Total Deals', 'Total Won Value', 'Win Rate %'];
        $rows = [];
        foreach ($data['monthly_summary'] as $m) {
            $rows[] = [
                $m['month'],
                $m['won'],
                $m['lost'],
                $m['total'],
                $m['formatted_value'],
                $m['win_rate'] . '%',
            ];
        }

        if ($format === 'pdf') {
            $kpis = [
                ['label' => 'Deals Won', 'value' => $data['kpis']['deals_won'], 'color_class' => 'text-emerald', 'sub' => $data['kpis']['won_growth']],
                ['label' => 'Deals Lost', 'value' => $data['kpis']['deals_lost'], 'color_class' => 'text-rose', 'sub' => $data['kpis']['lost_growth']],
                ['label' => 'Total Won Value', 'value' => $data['kpis']['formatted_total_won_value'], 'color_class' => 'text-blue', 'sub' => $data['kpis']['value_growth']],
                ['label' => 'Win Rate', 'value' => $data['kpis']['win_rate'] . '%', 'color_class' => 'text-purple', 'sub' => $data['kpis']['win_rate_growth']],
            ];
            return $this->generatePdf('Sales Trend & Performance Report', $headings, $rows, $kpis, $filename);
        }

        if ($format === 'csv') {
            return Excel::download(new CrmReportExport($rows, $headings, 'Sales Report'), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new CrmReportExport($rows, $headings, 'Sales Report'), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    private function buildSalesReportData(Request $request): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $query = $this->filterOpportunities($request, $startDate, $endDate);
        $allOpportunities = (clone $query)->get();

        $wonOpps = $allOpportunities->where('stage', 'Won');
        $lostOpps = $allOpportunities->where('stage', 'Lost');

        $dealsWon = $wonOpps->count();
        $dealsLost = $lostOpps->count();
        $totalWonValue = (float) $wonOpps->sum('estimated_value');
        $totalClosed = $dealsWon + $dealsLost;
        $winRate = $totalClosed > 0 ? round(($dealsWon / $totalClosed) * 100, 1) : 0;

        // Previous period comparisons for trend % badges (+12%, -4%, +18%, +3%)
        $days = max(1, $startDate->diffInDays($endDate));
        $prevStartDate = (clone $startDate)->subDays($days);
        $prevEndDate = (clone $startDate)->subDay();
        $prevOpps = $this->filterOpportunities($request, $prevStartDate, $prevEndDate)->get();
        $prevWon = $prevOpps->where('stage', 'Won')->count();
        $prevLost = $prevOpps->where('stage', 'Lost')->count();
        $prevWonVal = (float) $prevOpps->where('stage', 'Won')->sum('estimated_value');
        $prevClosed = $prevWon + $prevLost;
        $prevWinRate = $prevClosed > 0 ? round(($prevWon / $prevClosed) * 100, 1) : 0;

        $wonGrowth = $this->calculateGrowth($dealsWon, $prevWon);
        $lostGrowth = $this->calculateGrowth($dealsLost, $prevLost);
        $valGrowth = $this->calculateGrowth($totalWonValue, $prevWonVal);
        $winRateGrowth = $this->calculateGrowth($winRate, $prevWinRate);

        // Monthly Trend (Won count, Lost count, Total won value by month across 6 months)
        $monthsTrend = $this->buildMonthlySalesTrend($request, $endDate);

        return [
            'kpis' => [
                'deals_won'                 => $dealsWon,
                'won_growth'                => $wonGrowth,
                'deals_lost'                => $dealsLost,
                'lost_growth'               => $lostGrowth,
                'total_won_value'           => $totalWonValue,
                'formatted_total_won_value' => $this->formatIndianValue($totalWonValue),
                'value_growth'              => $valGrowth,
                'win_rate'                  => $winRate,
                'win_rate_growth'           => $winRateGrowth,
            ],
            'sales_trend' => [
                'labels'     => array_column($monthsTrend, 'month'),
                'won_deals'  => array_column($monthsTrend, 'won'),
                'lost_deals' => array_column($monthsTrend, 'lost'),
                'values'     => array_column($monthsTrend, 'value'),
                'formatted_values' => array_column($monthsTrend, 'formatted_value'),
            ],
            'monthly_summary' => $monthsTrend,
        ];
    }

    private function buildMonthlySalesTrend(Request $request, Carbon $endAnchor): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = (clone $endAnchor)->subMonths($i)->startOfMonth();
            $mEnd   = (clone $mStart)->endOfMonth();

            $opps = $this->filterOpportunities($request, $mStart, $mEnd)->get();
            $won = $opps->where('stage', 'Won')->count();
            $lost = $opps->where('stage', 'Lost')->count();
            $val = (float) $opps->where('stage', 'Won')->sum('estimated_value');
            $closed = $won + $lost;
            $rate = $closed > 0 ? round(($won / $closed) * 100, 1) : 0;

            $months[] = [
                'month'           => $mStart->format('M'),
                'month_full'      => $mStart->format('F Y'),
                'won'             => $won,
                'lost'            => $lost,
                'total'           => $closed,
                'value'           => $val,
                'formatted_value' => $this->formatIndianValue($val),
                'win_rate'        => $rate,
            ];
        }
        return $months;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. OPPORTUNITY REPORT (Screenshot 2)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Opportunity Report data: Top Cards, Pipeline Value by Stage, Pipeline by Salesperson, Pipeline by Customer.
     * GET /api/admin/crm/reports/opportunity
     */
    public function opportunityReport(Request $request): JsonResponse
    {
        $data = $this->buildOpportunityReportData($request);
        return $this->success($data, 'Opportunity report retrieved successfully');
    }

    /**
     * Export Opportunity Report (Excel, CSV, PDF).
     * GET /api/admin/crm/reports/opportunity/export
     */
    public function exportOpportunityReport(Request $request)
    {
        $this->authenticateFromToken($request);
        $data = $this->buildOpportunityReportData($request);
        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'opportunity_report_' . now()->format('YmdHis');

        $headings = ['Stage', 'Deals Count', 'Total Stage Value', 'Share %'];
        $rows = [];
        $totalVal = $data['kpis']['total_pipeline_value'] ?: 1;
        foreach ($data['pipeline_by_stage'] as $st) {
            $share = round(($st['value'] / $totalVal) * 100, 1);
            $rows[] = [
                $st['stage'],
                $st['count'],
                $st['formatted_value'],
                $share . '%',
            ];
        }

        if ($format === 'pdf') {
            $kpis = [
                ['label' => 'Total Pipeline', 'value' => $data['kpis']['formatted_total_pipeline'], 'color_class' => 'text-blue'],
                ['label' => 'Open Deals', 'value' => $data['kpis']['open_deals'], 'color_class' => 'text-purple'],
                ['label' => 'Won Deals', 'value' => $data['kpis']['won_deals'], 'color_class' => 'text-emerald'],
                ['label' => 'Lost Deals', 'value' => $data['kpis']['lost_deals'], 'color_class' => 'text-rose'],
            ];
            return $this->generatePdf('Opportunity Pipeline Report', $headings, $rows, $kpis, $filename);
        }

        if ($format === 'csv') {
            return Excel::download(new CrmReportExport($rows, $headings, 'Opportunity Report'), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new CrmReportExport($rows, $headings, 'Opportunity Report'), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    private function buildOpportunityReportData(Request $request): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $query = $this->filterOpportunities($request, $startDate, $endDate);
        $opportunities = $query->with(['assignedSalesperson', 'customer'])->get();

        $openOpps = $opportunities->whereNotIn('stage', ['Won', 'Lost']);
        $totalPipelineValue = (float) $openOpps->sum('estimated_value');
        $openDeals = $openOpps->count();
        $wonDeals = $opportunities->where('stage', 'Won')->count();
        $lostDeals = $opportunities->where('stage', 'Lost')->count();

        // 1. Pipeline Value by Stage (Bar chart in UI)
        $stagesList = ['New', 'Qualified', 'Proposal Sent', 'Negotiation', 'Won', 'Lost'];
        $stageColors = [
            'New'           => '#3B82F6',
            'Qualified'     => '#10B981',
            'Proposal Sent' => '#F59E0B',
            'Negotiation'   => '#8B5CF6',
            'Won'           => '#EC4899',
            'Lost'          => '#06B6D4',
        ];

        $pipelineByStage = [];
        foreach ($stagesList as $stage) {
            $stageItems = $opportunities->filter(fn($o) => strcasecmp(trim($o->stage), trim($stage)) === 0);
            $stageVal = (float) $stageItems->sum('estimated_value');
            $pipelineByStage[] = [
                'stage'           => $stage,
                'color'           => $stageColors[$stage] ?? '#64748B',
                'count'           => $stageItems->count(),
                'value'           => $stageVal,
                'formatted_value' => $this->formatIndianValue($stageVal),
            ];
        }

        // 2. Pipeline by Salesperson
        $salespersonGroup = $opportunities->groupBy(function ($opp) {
            return $opp->assigned_salesperson_name ?? 'Unassigned';
        });

        $pipelineBySalesperson = [];
        foreach ($salespersonGroup as $spName => $items) {
            $won = $items->where('stage', 'Won');
            $open = $items->whereNotIn('stage', ['Won', 'Lost']);
            $pipelineBySalesperson[] = [
                'salesperson'     => $spName,
                'won_value'       => (float) $won->sum('estimated_value'),
                'open_value'      => (float) $open->sum('estimated_value'),
                'total_value'     => (float) $items->sum('estimated_value'),
                'formatted_total' => $this->formatIndianValue((float) $items->sum('estimated_value')),
                'deals_count'     => $items->count(),
            ];
        }
        usort($pipelineBySalesperson, fn($a, $b) => $b['total_value'] <=> $a['total_value']);

        // 3. Pipeline by Customer
        $customerGroup = $opportunities->groupBy(function ($opp) {
            return $opp->customer_name ?: 'Unknown Customer';
        });

        $pipelineByCustomer = [];
        foreach ($customerGroup as $custName => $items) {
            $cVal = (float) $items->sum('estimated_value');
            $pipelineByCustomer[] = [
                'customer'        => $custName,
                'deals_count'     => $items->count(),
                'value'           => $cVal,
                'formatted_value' => $this->formatIndianValue($cVal),
            ];
        }
        usort($pipelineByCustomer, fn($a, $b) => $b['value'] <=> $a['value']);
        $pipelineByCustomer = array_slice($pipelineByCustomer, 0, 10);

        return [
            'kpis' => [
                'total_pipeline_value'     => $totalPipelineValue,
                'formatted_total_pipeline' => $this->formatIndianValue($totalPipelineValue),
                'open_deals'               => $openDeals,
                'won_deals'                => $wonDeals,
                'lost_deals'               => $lostDeals,
            ],
            'pipeline_by_stage'       => $pipelineByStage,
            'pipeline_by_salesperson' => $pipelineBySalesperson,
            'pipeline_by_customer'    => $pipelineByCustomer,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. SALES PERFORMANCE (Screenshot 3)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Sales Performance: Top cards (Won, Lost, Total Value, Avg Deal Size), Performance Trend area chart, Salesperson Leaderboard.
     * GET /api/admin/crm/reports/sales-performance
     */
    public function salesPerformanceReport(Request $request): JsonResponse
    {
        $data = $this->buildSalesPerformanceData($request);
        return $this->success($data, 'Sales performance report retrieved successfully');
    }

    /**
     * Export Sales Performance Report (Excel, CSV, PDF).
     * GET /api/admin/crm/reports/sales-performance/export
     */
    public function exportSalesPerformanceReport(Request $request)
    {
        $this->authenticateFromToken($request);
        $data = $this->buildSalesPerformanceData($request);
        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'sales_performance_' . now()->format('YmdHis');

        $headings = ['Rank', 'Salesperson', 'Won Value', 'Open Value', 'Total Value', 'Deals Count', 'Share %'];
        $rows = [];
        foreach ($data['salesperson_leaderboard'] as $lb) {
            $rows[] = [
                $lb['rank'],
                $lb['salesperson'],
                $lb['formatted_won'],
                $lb['formatted_open'],
                $lb['formatted_total'],
                $lb['deals_count'],
                $lb['share_percent'] . '%',
            ];
        }

        if ($format === 'pdf') {
            $kpis = [
                ['label' => 'Won Deals', 'value' => $data['kpis']['won_deals'], 'color_class' => 'text-emerald', 'sub' => $data['kpis']['won_growth']],
                ['label' => 'Lost Deals', 'value' => $data['kpis']['lost_deals'], 'color_class' => 'text-rose', 'sub' => $data['kpis']['lost_growth']],
                ['label' => 'Total Value', 'value' => $data['kpis']['formatted_total_value'], 'color_class' => 'text-blue', 'sub' => $data['kpis']['value_growth']],
                ['label' => 'Avg Deal Size', 'value' => $data['kpis']['formatted_avg_deal_size'], 'color_class' => 'text-purple'],
            ];
            return $this->generatePdf('Sales Performance & Leaderboard Report', $headings, $rows, $kpis, $filename);
        }

        if ($format === 'csv') {
            return Excel::download(new CrmReportExport($rows, $headings, 'Sales Performance'), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new CrmReportExport($rows, $headings, 'Sales Performance'), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    private function buildSalesPerformanceData(Request $request): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $query = $this->filterOpportunities($request, $startDate, $endDate);
        $opportunities = $query->with(['assignedSalesperson'])->get();

        $wonOpps = $opportunities->where('stage', 'Won');
        $lostOpps = $opportunities->where('stage', 'Lost');

        $wonCount = $wonOpps->count();
        $lostCount = $lostOpps->count();
        $totalWonValue = (float) $wonOpps->sum('estimated_value');
        $avgDealSize = $wonCount > 0 ? round($totalWonValue / $wonCount, 2) : 0;

        // Previous period for badges (+11%, -6%, +16%)
        $days = max(1, $startDate->diffInDays($endDate));
        $prevStartDate = (clone $startDate)->subDays($days);
        $prevEndDate = (clone $startDate)->subDay();
        $prevOpps = $this->filterOpportunities($request, $prevStartDate, $prevEndDate)->get();
        $prevWon = $prevOpps->where('stage', 'Won')->count();
        $prevLost = $prevOpps->where('stage', 'Lost')->count();
        $prevWonVal = (float) $prevOpps->where('stage', 'Won')->sum('estimated_value');

        $wonGrowth = $this->calculateGrowth($wonCount, $prevWon);
        $lostGrowth = $this->calculateGrowth($lostCount, $prevLost);
        $valGrowth = $this->calculateGrowth($totalWonValue, $prevWonVal);

        // Sales Performance Trend (6-month area curve)
        $trendData = $this->buildMonthlySalesTrend($request, $endDate);

        // Salesperson Leaderboard (#1, #2, #3, Won, Open, Total, Share %)
        $overallTotalValue = (float) $opportunities->sum('estimated_value') ?: 1;
        $leaderboard = [];

        $groupedBySalesperson = $opportunities->groupBy(function ($opp) {
            return $opp->assigned_salesperson_name ?? 'Unassigned';
        });

        foreach ($groupedBySalesperson as $spName => $items) {
            $wonVal = (float) $items->where('stage', 'Won')->sum('estimated_value');
            $openVal = (float) $items->whereNotIn('stage', ['Won', 'Lost'])->sum('estimated_value');
            $totVal = (float) $items->sum('estimated_value');
            $share = round(($totVal / $overallTotalValue) * 100, 1);

            $leaderboard[] = [
                'salesperson'     => $spName,
                'won_value'       => $wonVal,
                'formatted_won'   => $this->formatIndianValue($wonVal),
                'open_value'      => $openVal,
                'formatted_open'  => $this->formatIndianValue($openVal),
                'total_value'     => $totVal,
                'formatted_total' => $this->formatIndianValue($totVal),
                'share_percent'   => $share,
                'deals_count'     => $items->count(),
            ];
        }

        usort($leaderboard, fn($a, $b) => $b['total_value'] <=> $a['total_value']);
        foreach ($leaderboard as $idx => &$item) {
            $item['rank'] = '#' . ($idx + 1);
        }

        return [
            'kpis' => [
                'won_deals'                => $wonCount,
                'won_growth'               => $wonGrowth,
                'lost_deals'               => $lostCount,
                'lost_growth'              => $lostGrowth,
                'total_value'              => $totalWonValue,
                'formatted_total_value'    => $this->formatIndianValue($totalWonValue),
                'value_growth'             => $valGrowth,
                'avg_deal_size'            => $avgDealSize,
                'formatted_avg_deal_size'  => $this->formatIndianValue($avgDealSize),
            ],
            'performance_trend' => [
                'labels'     => array_column($trendData, 'month'),
                'won_deals'  => array_column($trendData, 'won'),
                'lost_deals' => array_column($trendData, 'lost'),
                'values'     => array_column($trendData, 'value'),
            ],
            'salesperson_leaderboard' => $leaderboard,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. ACTIVITY REPORT (Screenshot 4)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Activity Report: Total Activities, Completed, Overdue, Completion Rate, Status Breakdown donut, Activities by Type bar.
     * GET /api/admin/crm/reports/activity
     */
    public function activityReport(Request $request): JsonResponse
    {
        $data = $this->buildActivityReportData($request);
        return $this->success($data, 'Activity report retrieved successfully');
    }

    /**
     * Export Activity Report (Excel, CSV, PDF).
     * GET /api/admin/crm/reports/activity/export
     */
    public function exportActivityReport(Request $request)
    {
        $this->authenticateFromToken($request);
        $data = $this->buildActivityReportData($request);
        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'activity_report_' . now()->format('YmdHis');

        $headings = ['Activity Type', 'Total Count', 'Completed', 'Pending / Upcoming', 'Share %'];
        $rows = [];
        $totalAll = $data['kpis']['total_activities'] ?: 1;
        foreach ($data['activities_by_type'] as $at) {
            $share = round(($at['count'] / $totalAll) * 100, 1);
            $rows[] = [
                $at['type'],
                $at['count'],
                $at['completed'],
                $at['pending'],
                $share . '%',
            ];
        }

        if ($format === 'pdf') {
            $kpis = [
                ['label' => 'Total Activities', 'value' => $data['kpis']['total_activities'], 'color_class' => 'text-blue'],
                ['label' => 'Completed', 'value' => $data['kpis']['completed'], 'color_class' => 'text-emerald'],
                ['label' => 'Overdue', 'value' => $data['kpis']['overdue'], 'color_class' => 'text-rose'],
                ['label' => 'Completion Rate', 'value' => $data['kpis']['completion_rate'] . '%', 'color_class' => 'text-purple'],
            ];
            return $this->generatePdf('CRM Activity & Interaction Report', $headings, $rows, $kpis, $filename);
        }

        if ($format === 'csv') {
            return Excel::download(new CrmReportExport($rows, $headings, 'Activity Report'), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new CrmReportExport($rows, $headings, 'Activity Report'), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    private function buildActivityReportData(Request $request): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $query = Activity::query()
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString());

        if ($request->filled('salesperson_id')) {
            $query->where('assigned_employee_id', $request->salesperson_id);
        }

        $activities = $query->get();
        $totalActivities = $activities->count();

        $completed = $activities->filter(function ($a) {
            return strtolower($a->status) === 'completed';
        })->count();

        $cancelled = $activities->filter(function ($a) {
            return in_array(strtolower($a->status), ['cancelled', 'canceled']);
        })->count();

        $now = now();
        $overdue = $activities->filter(function ($a) use ($now) {
            return strtolower($a->status) !== 'completed' &&
                   $a->end_date_time &&
                   Carbon::parse($a->end_date_time)->lt($now);
        })->count();

        $upcoming = max(0, $totalActivities - $completed - $cancelled - $overdue);

        $completionRate = $totalActivities > 0 ? round(($completed / $totalActivities) * 100) : 0;
        $overdueRate = $totalActivities > 0 ? round(($overdue / $totalActivities) * 100) : 0;
        $upcomingRate = $totalActivities > 0 ? round(($upcoming / $totalActivities) * 100) : 0;
        $cancelledRate = $totalActivities > 0 ? round(($cancelled / $totalActivities) * 100) : 0;

        // Activity by type breakdown
        $types = ['Call', 'Meeting', 'Email', 'Task', 'Site Visit'];
        $activitiesByType = [];
        foreach ($types as $type) {
            $typeItems = $activities->filter(fn($a) => strcasecmp(trim($a->activity_type ?? ''), trim($type)) === 0);
            $activitiesByType[] = [
                'type'      => $type,
                'count'     => $typeItems->count(),
                'completed' => $typeItems->filter(fn($a) => strtolower($a->status) === 'completed')->count(),
                'pending'   => $typeItems->filter(fn($a) => strtolower($a->status) !== 'completed')->count(),
            ];
        }

        return [
            'kpis' => [
                'total_activities' => $totalActivities,
                'completed'        => $completed,
                'overdue'          => $overdue,
                'completion_rate'  => $completionRate,
            ],
            'status_breakdown' => [
                'completed'      => $completed,
                'completed_pct'  => $completionRate,
                'upcoming'       => $upcoming,
                'upcoming_pct'   => $upcomingRate,
                'overdue'        => $overdue,
                'overdue_pct'    => $overdueRate,
                'cancelled'      => $cancelled,
                'cancelled_pct'  => $cancelledRate,
            ],
            'activities_by_type' => $activitiesByType,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. QUOTATION REPORT (Screenshot 5)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Quotation Report: Total Quotations, Total Value, Accepted Value, Acceptance Rate, Quotations by Status bar, Quotations Over Time line chart.
     * GET /api/admin/crm/reports/quotation
     */
    public function quotationReport(Request $request): JsonResponse
    {
        $data = $this->buildQuotationReportData($request);
        return $this->success($data, 'Quotation report retrieved successfully');
    }

    /**
     * Export Quotation Report (Excel, CSV, PDF).
     * GET /api/admin/crm/reports/quotation/export
     */
    public function exportQuotationReport(Request $request)
    {
        $this->authenticateFromToken($request);
        $data = $this->buildQuotationReportData($request);
        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'quotation_report_' . now()->format('YmdHis');

        $headings = ['Status', 'Count', 'Total Value', 'Share %'];
        $rows = [];
        $totVal = $data['kpis']['total_value'] ?: 1;
        foreach ($data['quotations_by_status'] as $qs) {
            $share = round(($qs['value'] / $totVal) * 100, 1);
            $rows[] = [
                $qs['status'],
                $qs['count'],
                $qs['formatted_value'],
                $share . '%',
            ];
        }

        if ($format === 'pdf') {
            $kpis = [
                ['label' => 'Total Quotations', 'value' => $data['kpis']['total_quotations'], 'color_class' => 'text-blue'],
                ['label' => 'Total Value', 'value' => $data['kpis']['formatted_total_value'], 'color_class' => 'text-purple'],
                ['label' => 'Accepted Value', 'value' => $data['kpis']['formatted_accepted_value'], 'color_class' => 'text-emerald'],
                ['label' => 'Acceptance Rate', 'value' => $data['kpis']['acceptance_rate'] . '%', 'color_class' => 'text-blue'],
            ];
            return $this->generatePdf('Quotation Analytics Report', $headings, $rows, $kpis, $filename);
        }

        if ($format === 'csv') {
            return Excel::download(new CrmReportExport($rows, $headings, 'Quotation Report'), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new CrmReportExport($rows, $headings, 'Quotation Report'), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    private function buildQuotationReportData(Request $request): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $query = Quotation::query()
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString());

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $quotations = $query->get();
        $totalQuotations = $quotations->count();
        $totalValue = (float) $quotations->sum('grand_total');

        $accepted = $quotations->filter(fn($q) => in_array(strtolower($q->status), ['accepted', 'approved']));
        $acceptedCount = $accepted->count();
        $acceptedValue = (float) $accepted->sum('grand_total');
        $acceptanceRate = $totalQuotations > 0 ? round(($acceptedCount / $totalQuotations) * 100) : 0;

        // Status breakdown (Draft, Sent, Viewed, Accepted, Rejected, Expired)
        $statuses = [
            'Draft'    => '#3B82F6',
            'Sent'     => '#10B981',
            'Viewed'   => '#F59E0B',
            'Accepted' => '#8B5CF6',
            'Rejected' => '#EC4899',
            'Expired'  => '#06B6D4',
        ];

        $quotationsByStatus = [];
        foreach ($statuses as $st => $color) {
            $items = $quotations->filter(fn($q) => strcasecmp(trim($q->status), trim($st)) === 0);
            $val = (float) $items->sum('grand_total');
            $quotationsByStatus[] = [
                'status'          => $st,
                'color'           => $color,
                'count'           => $items->count(),
                'value'           => $val,
                'formatted_value' => $this->formatIndianValue($val),
            ];
        }

        // Quotations Over Time (6 months Count vs Value)
        $monthsOverTime = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = (clone $endDate)->subMonths($i)->startOfMonth();
            $mEnd   = (clone $mStart)->endOfMonth();

            $subQuery = Quotation::query()
                ->whereDate('created_at', '>=', $mStart->toDateString())
                ->whereDate('created_at', '<=', $mEnd->toDateString());

            if ($request->filled('customer_id')) {
                $subQuery->where('customer_id', $request->customer_id);
            }

            $mQuotes = $subQuery->get();
            $mVal = (float) $mQuotes->sum('grand_total');

            $monthsOverTime[] = [
                'month'           => $mStart->format('M'),
                'count'           => $mQuotes->count(),
                'value'           => $mVal,
                'formatted_value' => $this->formatIndianValue($mVal),
            ];
        }

        return [
            'kpis' => [
                'total_quotations'         => $totalQuotations,
                'total_value'              => $totalValue,
                'formatted_total_value'    => $this->formatIndianValue($totalValue),
                'accepted_value'           => $acceptedValue,
                'formatted_accepted_value' => $this->formatIndianValue($acceptedValue),
                'acceptance_rate'          => $acceptanceRate,
            ],
            'quotations_by_status'  => $quotationsByStatus,
            'quotations_over_time' => [
                'labels'           => array_column($monthsOverTime, 'month'),
                'counts'           => array_column($monthsOverTime, 'count'),
                'values'           => array_column($monthsOverTime, 'value'),
                'formatted_values' => array_column($monthsOverTime, 'formatted_value'),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. CUSTOMER REPORT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Customer Report: Total Customers, Active Customers, New This Month, Retention/Growth, Type & Industry breakdown.
     * GET /api/admin/crm/reports/customer
     */
    public function customerReport(Request $request): JsonResponse
    {
        $data = $this->buildCustomerReportData($request);
        return $this->success($data, 'Customer report retrieved successfully');
    }

    /**
     * Export Customer Report (Excel, CSV, PDF).
     * GET /api/admin/crm/reports/customer/export
     */
    public function exportCustomerReport(Request $request)
    {
        $this->authenticateFromToken($request);
        $data = $this->buildCustomerReportData($request);
        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'customer_report_' . now()->format('YmdHis');

        $headings = ['Category / Industry', 'Customer Count', 'Active Count', 'Share %'];
        $rows = [];
        $totalCust = $data['kpis']['total_customers'] ?: 1;
        foreach ($data['by_industry'] as $ind) {
            $share = round(($ind['count'] / $totalCust) * 100, 1);
            $rows[] = [
                $ind['name'],
                $ind['count'],
                $ind['active'],
                $share . '%',
            ];
        }

        if ($format === 'pdf') {
            $kpis = [
                ['label' => 'Total Customers', 'value' => $data['kpis']['total_customers'], 'color_class' => 'text-blue'],
                ['label' => 'Active Customers', 'value' => $data['kpis']['active_customers'], 'color_class' => 'text-emerald'],
                ['label' => 'New Customers', 'value' => $data['kpis']['new_customers'], 'color_class' => 'text-purple'],
                ['label' => 'Active Ratio', 'value' => $data['kpis']['active_ratio'] . '%', 'color_class' => 'text-blue'],
            ];
            return $this->generatePdf('Customer Growth & Industry Analytics Report', $headings, $rows, $kpis, $filename);
        }

        if ($format === 'csv') {
            return Excel::download(new CrmReportExport($rows, $headings, 'Customer Report'), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new CrmReportExport($rows, $headings, 'Customer Report'), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    private function buildCustomerReportData(Request $request): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $query = Customer::query();
        if ($request->filled('customer_id')) {
            $query->where('id', $request->customer_id);
        }
        if ($request->filled('salesperson_id')) {
            $query->where('account_manager_id', $request->salesperson_id);
        }

        $allCustomers = $query->get();
        $totalCustomers = $allCustomers->count();
        $activeCustomers = $allCustomers->filter(fn($c) => strtolower($c->customer_status) === 'active')->count();

        $newCustomers = $allCustomers->filter(function ($c) use ($startDate, $endDate) {
            return $c->created_at &&
                   $c->created_at->gte($startDate) &&
                   $c->created_at->lte($endDate);
        })->count();

        $activeRatio = $totalCustomers > 0 ? round(($activeCustomers / $totalCustomers) * 100, 1) : 0;

        // Breakdown by Customer Type
        $byType = $allCustomers->groupBy('customer_type')->map(function ($group, $type) {
            return [
                'type'   => $type ?: 'Other',
                'count'  => $group->count(),
                'active' => $group->filter(fn($c) => strtolower($c->customer_status) === 'active')->count(),
            ];
        })->values();

        // Breakdown by Industry
        $byIndustry = $allCustomers->groupBy('industry')->map(function ($group, $ind) {
            return [
                'name'   => $ind ?: 'General',
                'count'  => $group->count(),
                'active' => $group->filter(fn($c) => strtolower($c->customer_status) === 'active')->count(),
            ];
        })->sortByDesc('count')->values()->take(10)->toArray();

        return [
            'kpis' => [
                'total_customers'  => $totalCustomers,
                'active_customers' => $activeCustomers,
                'new_customers'    => $newCustomers,
                'active_ratio'     => $activeRatio,
            ],
            'by_type'     => $byType,
            'by_industry' => $byIndustry,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. LEAD REPORT (User specifically requested: "Also i need leads reports section apis also which is not in this ui")
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Lead Report: Total Leads, Converted, Qualified, Conversion Rate, Leads by Source, Leads by Status.
     * GET /api/admin/crm/reports/lead
     */
    public function leadReport(Request $request): JsonResponse
    {
        $data = $this->buildLeadReportData($request);
        return $this->success($data, 'Lead report retrieved successfully');
    }

    /**
     * Export Lead Report (Excel, CSV, PDF).
     * GET /api/admin/crm/reports/lead/export
     */
    public function exportLeadReport(Request $request)
    {
        $this->authenticateFromToken($request);
        $data = $this->buildLeadReportData($request);
        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'lead_report_' . now()->format('YmdHis');

        $headings = ['Lead Source', 'Total Leads', 'Converted Leads', 'Conversion Rate %', 'Expected Value'];
        $rows = [];
        foreach ($data['leads_by_source'] as $src) {
            $rows[] = [
                $src['source'],
                $src['count'],
                $src['converted'],
                $src['conversion_rate'] . '%',
                $src['formatted_value'],
            ];
        }

        if ($format === 'pdf') {
            $kpis = [
                ['label' => 'Total Leads', 'value' => $data['kpis']['total_leads'], 'color_class' => 'text-blue'],
                ['label' => 'Converted', 'value' => $data['kpis']['converted_leads'], 'color_class' => 'text-emerald'],
                ['label' => 'Qualified', 'value' => $data['kpis']['qualified_leads'], 'color_class' => 'text-purple'],
                ['label' => 'Conversion Rate', 'value' => $data['kpis']['conversion_rate'] . '%', 'color_class' => 'text-blue'],
            ];
            return $this->generatePdf('Leads & Inbound Channel Analytics Report', $headings, $rows, $kpis, $filename);
        }

        if ($format === 'csv') {
            return Excel::download(new CrmReportExport($rows, $headings, 'Lead Report'), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new CrmReportExport($rows, $headings, 'Lead Report'), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    private function buildLeadReportData(Request $request): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);

        $query = Lead::query()
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString());

        if ($request->filled('salesperson_id')) {
            $query->where('assigned_salesperson_id', $request->salesperson_id);
        }
        if ($request->filled('team')) {
            $query->where('sales_team', $request->team);
        }
        if ($request->filled('lead_source')) {
            $query->where('lead_source', $request->lead_source);
        }
        if ($request->filled('stage') || $request->filled('status')) {
            $status = $request->input('stage', $request->input('status'));
            $query->where('lead_status', $status);
        }

        $leads = $query->get();
        $totalLeads = $leads->count();

        $converted = $leads->filter(fn($l) => in_array(strtolower($l->lead_status), ['converted', 'closed', 'won']));
        $convertedCount = $converted->count();

        $qualified = $leads->filter(fn($l) => in_array(strtolower($l->lead_status), ['qualified', 'hot', 'in progress']));
        $qualifiedCount = $qualified->count();

        $conversionRate = $totalLeads > 0 ? round(($convertedCount / $totalLeads) * 100, 1) : 0;
        $totalExpectedValue = (float) $leads->sum('expected_value');

        // Leads by Source
        $sourcesGroup = $leads->groupBy('lead_source');
        $leadsBySource = [];
        foreach ($sourcesGroup as $srcName => $items) {
            $conv = $items->filter(fn($l) => in_array(strtolower($l->lead_status), ['converted', 'closed', 'won']))->count();
            $val = (float) $items->sum('expected_value');
            $rate = $items->count() > 0 ? round(($conv / $items->count()) * 100, 1) : 0;

            $leadsBySource[] = [
                'source'          => $srcName ?: 'Direct / Unknown',
                'count'           => $items->count(),
                'converted'       => $conv,
                'conversion_rate' => $rate,
                'value'           => $val,
                'formatted_value' => $this->formatIndianValue($val),
            ];
        }
        usort($leadsBySource, fn($a, $b) => $b['count'] <=> $a['count']);

        // Leads by Status
        $statusGroup = $leads->groupBy('lead_status');
        $leadsByStatus = [];
        foreach ($statusGroup as $stName => $items) {
            $leadsByStatus[] = [
                'status' => $stName ?: 'New',
                'count'  => $items->count(),
            ];
        }

        return [
            'kpis' => [
                'total_leads'                => $totalLeads,
                'converted_leads'            => $convertedCount,
                'qualified_leads'            => $qualifiedCount,
                'conversion_rate'            => $conversionRate,
                'total_expected_value'       => $totalExpectedValue,
                'formatted_expected_value'   => $this->formatIndianValue($totalExpectedValue),
            ],
            'leads_by_source' => $leadsBySource,
            'leads_by_status' => $leadsByStatus,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Shared Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Resolve Date Range from filters (This Month, Last Month, This Quarter, This Year, Custom).
     */
    private function resolveDateRange(Request $request): array
    {
        $now = Carbon::now();
        $dateRange = strtolower($request->input('date_range', 'this_month'));

        // Custom range if provided
        if ($request->filled('date_from') && $request->filled('date_to')) {
            return [
                Carbon::parse($request->date_from)->startOfDay(),
                Carbon::parse($request->date_to)->endOfDay(),
            ];
        }

        switch ($dateRange) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

            case 'yesterday':
                return [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()];

            case 'this_week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];

            case 'last_week':
                return [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()];

            case 'last_month':
                return [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()];

            case 'this_quarter':
                return [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()];

            case 'this_year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];

            case 'last_year':
                return [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()];

            case 'this_month':
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }
    }

    /**
     * Filter Opportunity Eloquent query based on all UI filters.
     */
    private function filterOpportunities(Request $request, Carbon $startDate, Carbon $endDate)
    {
        $query = Opportunity::query()
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString());

        if ($request->filled('salesperson_id')) {
            $query->where('assigned_salesperson_id', $request->salesperson_id);
        }

        if ($request->filled('team')) {
            $query->where('sales_team', $request->team);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('lead_source')) {
            $query->where('lead_source', $request->lead_source);
        }

        if ($request->filled('stage')) {
            $query->where('stage', $request->stage);
        }

        return $query;
    }

    /**
     * Calculate percentage growth string e.g. "+12%" or "-4%".
     */
    private function calculateGrowth(float $current, float $previous): string
    {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }
        $pct = round((($current - $previous) / $previous) * 100);
        return ($pct >= 0 ? '+' : '') . $pct . '%';
    }

    /**
     * Format currency in Indian format (₹25.10L, ₹41K, ₹1.37Cr).
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
     * Generate standard branded PDF response.
     */
    private function generatePdf(string $title, array $headings, array $rows, array $kpis, string $filename)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $company = Company::first();

        $pdf = Pdf::loadView('reports.crm_report_pdf', [
            'title'    => $title,
            'headings' => $headings,
            'data'     => $rows,
            'kpis'     => $kpis,
            'company'  => $company,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename . '.pdf');
    }

    /**
     * Authenticate token from Header or query parameter.
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
