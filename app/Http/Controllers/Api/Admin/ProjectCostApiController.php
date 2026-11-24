<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\ProjectCostExport;
use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTimeLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ProjectCostApiController extends ApiController
{
    /**
     * Get Project-wise Cost Summary with Date Filtering.
     * GET /api/admin/projects/cost
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess($request);

        $range = $this->resolveDateRange($request);
        $data = $this->buildProjectCostData($request, $range['start_date'], $range['end_date']);

        return $this->success([
            'filter'       => $range['filter'],
            'start_date'   => $range['start_date']->format('Y-m-d'),
            'end_date'     => $range['end_date']->format('Y-m-d'),
            'period_label' => $range['label'],
            'currency'     => 'AED',
            'summary'      => $data['summary'],
            'projects'     => $data['projects'],
        ], 'Project cost summary retrieved successfully');
    }

    /**
     * Get Detailed Cost Breakdown for a Specific Project.
     * Includes employee-wise breakdown and daily hours timeline.
     * GET /api/admin/projects/cost/{projectId}
     */
    public function show(Request $request, $projectId): JsonResponse
    {
        $this->authorizeAccess($request);

        $project = Project::with(['projectManager.user.designation', 'teamLead.user.designation'])->find($projectId);
        if (!$project) {
            return $this->error('Project not found', 404);
        }

        $range = $this->resolveDateRange($request);
        $details = $this->buildSingleProjectCostDetails($project, $range['start_date'], $range['end_date'], $range['label']);

        return $this->success($details, 'Project cost details retrieved successfully');
    }

    /**
     * Unified Export Endpoint (Excel, CSV, PDF).
     * GET /api/admin/projects/cost/export?format=xlsx|csv|pdf
     */
    public function export(Request $request)
    {
        $this->authenticateFromToken($request);
        $this->authorizeAccess($request);

        $format = strtolower($request->input('format', 'xlsx'));
        if ($format === 'pdf') {
            return $this->exportPdf($request);
        }

        return $this->exportExcel($request);
    }

    /**
     * Export to Excel or CSV.
     * GET /api/admin/projects/cost/export/excel?format=xlsx|csv
     */
    public function exportExcel(Request $request)
    {
        $this->authenticateFromToken($request);
        $this->authorizeAccess($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $range = $this->resolveDateRange($request);
        $data = $this->buildProjectCostData($request, $range['start_date'], $range['end_date']);

        $rows = [];
        foreach ($data['projects'] as $proj) {
            $budgetFormatted = $proj['has_budget'] ? number_format($proj['budget_aed'], 2) : 'No budget set';
            $varianceFormatted = $proj['has_budget'] ? number_format($proj['variance_aed'], 2) : '-';

            $rows[] = [
                $proj['project_name'],
                $proj['client_name'] ?? '-',
                $proj['status'] ?? 'Active',
                $proj['employees_count'],
                $proj['total_hours_formatted'],
                number_format($proj['total_cost_aed'], 2),
                $budgetFormatted,
                $varianceFormatted,
            ];
        }

        // Summary row at the bottom
        $summary = $data['summary'];
        $rows[] = [
            'TOTAL SUMMARY',
            '-',
            '-',
            $summary['total_employees'],
            $summary['total_hours_formatted'],
            number_format($summary['total_cost'], 2),
            number_format($summary['total_budget'], 2),
            number_format($summary['total_variance'], 2),
        ];

        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'project_cost_report_' . now()->format('YmdHis');

        if ($format === 'csv') {
            return Excel::download(new ProjectCostExport($rows, 'Project Cost Report'), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new ProjectCostExport($rows, 'Project Cost Report'), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export to PDF.
     * GET /api/admin/projects/cost/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $this->authenticateFromToken($request);
        $this->authorizeAccess($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $range = $this->resolveDateRange($request);
        $data = $this->buildProjectCostData($request, $range['start_date'], $range['end_date']);

        $filename = 'project_cost_report_' . now()->format('YmdHis') . '.pdf';

        $pdf = Pdf::loadView('reports.project_cost_pdf', [
            'title'        => 'Project Cost Report',
            'period_label' => $range['label'],
            'summary'      => $data['summary'],
            'projects'     => $data['projects'],
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIVATE DATA BUILDERS & HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build Project-wise Summary Data.
     */
    private function buildProjectCostData(Request $request, Carbon $startDate, Carbon $endDate): array
    {
        $projectsQuery = Project::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $projectsQuery->where(function ($q) use ($search) {
                $q->where('project_name', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $projectsQuery->where('status', $request->status);
        }

        $projects = $projectsQuery->orderBy('project_name')->get();

        // Load all time logs in the date window for efficiency
        $timeLogs = ProjectTimeLog::with(['employee.user.designation'])
            ->whereIn('project_id', $projects->pluck('id'))
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->groupBy('project_id');

        // Preload pivot employee assignments
        $assignedEmployeesMap = DB::table('employee_project')
            ->whereIn('project_id', $projects->pluck('id'))
            ->whereNull('deleted_at')
            ->select('project_id', 'employee_id', 'hourly_rate')
            ->get()
            ->groupBy('project_id');

        $projectList = [];
        $grandTotalMinutes = 0;
        $grandTotalCost = 0.0;
        $grandTotalBudget = 0.0;
        $allUniqueEmployees = [];

        foreach ($projects as $project) {
            $logs = $timeLogs->get($project->id, collect());
            $assigned = $assignedEmployeesMap->get($project->id, collect());

            $projectMinutes = 0;
            $projectCost = 0.0;
            $projectEmployeeIds = [];

            // Group logs by employee
            $logsByEmployee = $logs->groupBy('employee_id');

            foreach ($logsByEmployee as $empId => $empLogs) {
                $projectEmployeeIds[$empId] = true;
                $allUniqueEmployees[$empId] = true;

                $empMinutes = $empLogs->sum('time_taken_minutes');
                $projectMinutes += $empMinutes;

                // Resolve hourly rate for this employee on this project
                $firstLog = $empLogs->first();
                $employeeModel = $firstLog ? $firstLog->employee : null;
                $pivotOverride = $assigned->firstWhere('employee_id', $empId);
                $hourlyRate = $this->resolveEmployeeHourlyRate($employeeModel, $pivotOverride?->hourly_rate);

                $empHours = $empMinutes / 60;
                $projectCost += ($empHours * $hourlyRate);
            }

            // Also account for assigned employees even if 0 logs logged
            foreach ($assigned as $assign) {
                $projectEmployeeIds[$assign->employee_id] = true;
                $allUniqueEmployees[$assign->employee_id] = true;
            }

            $hasBudget = !is_null($project->budget) && (float) $project->budget > 0;
            $budget = $hasBudget ? (float) $project->budget : null;
            $variance = $hasBudget ? ($budget - $projectCost) : null;
            $isOverBudget = $hasBudget ? ($projectCost > $budget) : false;

            if ($hasBudget) {
                $grandTotalBudget += $budget;
            }
            $grandTotalCost += $projectCost;
            $grandTotalMinutes += $projectMinutes;

            $hoursFormatted = $this->formatMinutesToHoursString($projectMinutes);

            $projectList[] = [
                'id'                    => $project->id,
                'project_name'          => $project->project_name,
                'client_name'           => $project->client_name,
                'status'                => $project->status ?? 'Active',
                'employees_count'       => count($projectEmployeeIds),
                'total_minutes'         => $projectMinutes,
                'total_hours_decimal'   => round($projectMinutes / 60, 2),
                'total_hours_formatted' => $hoursFormatted,
                'total_cost_aed'        => round($projectCost, 2),
                'total_cost_formatted'  => 'AED ' . number_format($projectCost, 2),
                'budget_aed'            => $budget,
                'budget_formatted'      => $hasBudget ? 'AED ' . number_format($budget, 2) : 'No budget set',
                'has_budget'            => $hasBudget,
                'variance_aed'          => $variance !== null ? round($variance, 2) : null,
                'variance_formatted'    => $variance !== null ? (($variance < 0 ? '-' : '') . 'AED ' . number_format(abs($variance), 2)) : '-',
                'is_over_budget'        => $isOverBudget,
            ];
        }

        $grandTotalVariance = $grandTotalBudget - $grandTotalCost;

        $summary = [
            'total_employees'        => count($allUniqueEmployees),
            'total_minutes'          => $grandTotalMinutes,
            'total_hours_decimal'    => round($grandTotalMinutes / 60, 2),
            'total_hours_formatted'  => $this->formatMinutesToHoursString($grandTotalMinutes),
            'total_cost'             => round($grandTotalCost, 2),
            'total_cost_formatted'   => 'AED ' . number_format($grandTotalCost, 2),
            'total_budget'           => round($grandTotalBudget, 2),
            'total_budget_formatted' => 'AED ' . number_format($grandTotalBudget, 2),
            'total_variance'         => round($grandTotalVariance, 2),
            'total_variance_formatted' => ($grandTotalVariance < 0 ? '-' : '') . 'AED ' . number_format(abs($grandTotalVariance), 2),
            'is_over_budget'         => $grandTotalCost > $grandTotalBudget,
        ];

        return [
            'summary'  => $summary,
            'projects' => $projectList,
        ];
    }

    /**
     * Build Single Project Cost Details (Modal / Drawer view in UI Image 2).
     */
    private function buildSingleProjectCostDetails(Project $project, Carbon $startDate, Carbon $endDate, string $periodLabel): array
    {
        $timeLogs = ProjectTimeLog::with(['employee.user.designation'])
            ->where('project_id', $project->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('date')
            ->get();

        $assignedPivot = DB::table('employee_project')
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->select('employee_id', 'hourly_rate')
            ->get()
            ->keyBy('employee_id');

        $assignedEmployeeIds = $assignedPivot->keys()->toArray();
        $loggedEmployeeIds = $timeLogs->pluck('employee_id')->unique()->toArray();
        $allEmpIds = array_values(array_unique(array_merge($assignedEmployeeIds, $loggedEmployeeIds)));

        $employeesMap = Employee::with('user.designation')
            ->whereIn('id', $allEmpIds)
            ->get()
            ->keyBy('id');

        $employeeBreakdown = [];
        $totalProjectMinutes = 0;
        $totalProjectCost = 0.0;

        foreach ($allEmpIds as $empId) {
            $emp = $employeesMap->get($empId);
            if (!$emp) continue;

            $empLogs = $timeLogs->where('employee_id', $empId);
            $minutes = $empLogs->sum('time_taken_minutes');
            $hours = $minutes / 60;

            $pivotRate = $assignedPivot->get($empId)?->hourly_rate;
            $hourlyRate = $this->resolveEmployeeHourlyRate($emp, $pivotRate);
            $cost = $hours * $hourlyRate;

            $totalProjectMinutes += $minutes;
            $totalProjectCost += $cost;

            $fullName = trim($emp->first_name . ' ' . ($emp->last_name ?? ''));
            $initial = strtoupper(substr($emp->first_name, 0, 1) ?: 'E');
            $designation = optional(optional($emp->user)->designation)->name ?? 'Project Member';

            $hoursStr = floor($hours) . 'h' . (($minutes % 60) > 0 ? (' ' . ($minutes % 60) . 'm') : '');

            $employeeBreakdown[] = [
                'id'                   => $emp->id,
                'employee_id'          => $emp->employee_id,
                'name'                 => $fullName,
                'initials'             => $initial,
                'avatar'               => $emp->avatar_url ?? null,
                'designation'          => $designation,
                'total_minutes'        => $minutes,
                'hours_decimal'        => round($hours, 2),
                'hours_formatted'      => $hoursStr,
                'hourly_rate'          => round($hourlyRate, 2),
                'hourly_rate_formatted'=> 'AED ' . number_format($hourlyRate, 2),
                'cost'                 => round($cost, 2),
                'cost_formatted'       => 'AED ' . number_format($cost, 2),
            ];
        }

        // Sort employees by cost descending
        usort($employeeBreakdown, fn($a, $b) => $b['cost'] <=> $a['cost']);

        // Build Daily Hours timeline for the period (chart in Image 2)
        $dailyLogsGroup = $timeLogs->groupBy(fn($l) => Carbon::parse($l->date)->format('Y-m-d'));
        $dailyHours = [];

        $period = CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayLogs = $dailyLogsGroup->get($dateStr, collect());

            $dayMinutes = $dayLogs->sum('time_taken_minutes');
            $dayHours = round($dayMinutes / 60, 2);
            $dayCost = 0.0;

            foreach ($dayLogs as $log) {
                $rate = $this->resolveEmployeeHourlyRate($log->employee, $assignedPivot->get($log->employee_id)?->hourly_rate);
                $dayCost += ($log->time_taken_minutes / 60) * $rate;
            }

            $dailyHours[] = [
                'date'             => $dateStr,
                'day'              => $date->format('d'),
                'day_name'         => $date->format('D'),
                'total_minutes'    => $dayMinutes,
                'hours'            => $dayHours,
                'cost'             => round($dayCost, 2),
                'employees_count'  => $dayLogs->pluck('employee_id')->unique()->count(),
            ];
        }

        $hasBudget = !is_null($project->budget) && (float) $project->budget > 0;
        $budget = $hasBudget ? (float) $project->budget : null;
        $variance = $hasBudget ? ($budget - $totalProjectCost) : null;

        return [
            'project' => [
                'id'           => $project->id,
                'project_name' => $project->project_name,
                'client_name'  => $project->client_name,
                'status'       => $project->status ?? 'Active',
                'start_date'   => $project->start_date,
                'end_date'     => $project->end_date,
            ],
            'period_label' => $periodLabel,
            'currency'     => 'AED',
            'summary'      => [
                'employees_count'       => count($allEmpIds),
                'total_minutes'         => $totalProjectMinutes,
                'total_hours_decimal'   => round($totalProjectMinutes / 60, 2),
                'total_hours_formatted' => $this->formatMinutesToHoursString($totalProjectMinutes),
                'total_cost'            => round($totalProjectCost, 2),
                'total_cost_formatted'  => 'AED ' . number_format($totalProjectCost, 2),
                'budget'                => $budget,
                'budget_formatted'      => $hasBudget ? 'AED ' . number_format($budget, 2) : 'No budget set',
                'has_budget'            => $hasBudget,
                'variance'              => $variance !== null ? round($variance, 2) : null,
                'variance_formatted'    => $variance !== null ? (($variance < 0 ? '-' : '') . 'AED ' . number_format(abs($variance), 2)) : '-',
                'is_over_budget'        => $hasBudget ? ($totalProjectCost > $budget) : false,
            ],
            'employee_breakdown' => $employeeBreakdown,
            'daily_hours'        => $dailyHours,
        ];
    }

    /**
     * Resolve date filtering based on query parameters.
     */
    private function resolveDateRange(Request $request): array
    {
        $filter = strtolower($request->input('filter', 'this_month'));

        switch ($filter) {
            case 'last_month':
                $start = now()->subMonth()->startOfMonth();
                $end = now()->subMonth()->endOfMonth();
                $label = 'Last Month (' . $start->format('M Y') . ')';
                break;

            case 'this_quarter':
                $start = now()->startOfQuarter();
                $end = now()->endOfQuarter();
                $label = 'This Quarter (' . $start->format('M') . ' - ' . $end->format('M Y') . ')';
                break;

            case 'project_to_date':
                $start = Carbon::create(2020, 1, 1);
                $end = now()->endOfDay();
                $label = 'Project to Date';
                break;

            case 'custom':
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    $start = Carbon::parse($request->start_date)->startOfDay();
                    $end = Carbon::parse($request->end_date)->endOfDay();
                    $label = 'Period: Custom (' . $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y') . ')';
                } else {
                    $start = now()->startOfMonth();
                    $end = now()->endOfMonth();
                    $label = 'Period: Custom';
                }
                break;

            case 'this_month':
            default:
                $filter = 'this_month';
                $start = now()->startOfMonth();
                $end = now()->endOfMonth();
                $label = 'This Month (' . $start->format('M Y') . ')';
                break;
        }

        // Direct date range override if supplied
        if ($request->filled('start_date') && $request->filled('end_date') && $filter !== 'custom') {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();
            $label = 'Custom Range (' . $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y') . ')';
            $filter = 'custom';
        }

        return [
            'filter'     => $filter,
            'start_date' => $start,
            'end_date'   => $end,
            'label'      => $label,
        ];
    }

    /**
     * Resolve employee hourly rate with fallback logic.
     */
    private function resolveEmployeeHourlyRate(?Employee $employee, $pivotHourlyRate = null): float
    {
        if ($pivotHourlyRate !== null && (float) $pivotHourlyRate > 0) {
            return (float) $pivotHourlyRate;
        }

        if ($employee && !is_null($employee->hourly_rate) && (float) $employee->hourly_rate > 0) {
            return (float) $employee->hourly_rate;
        }

        // Fallback by designation
        $designation = strtolower(optional(optional($employee?->user)->designation)->name ?? '');

        if (str_contains($designation, 'project manager') || str_contains($designation, 'lead architect')) {
            return 150.00;
        }
        if (str_contains($designation, 'civil engineer') || str_contains($designation, 'structural engineer') || str_contains($designation, 'senior')) {
            return 120.00;
        }
        if (str_contains($designation, 'bim modeler') || str_contains($designation, 'draftsman')) {
            return 90.00;
        }
        if (str_contains($designation, 'supervisor') || str_contains($designation, 'site supervisor')) {
            return 100.00;
        }

        return 100.00; // Default baseline AED per hour
    }

    /**
     * Format minutes to "1240h 30m" or "2850h" string.
     */
    private function formatMinutesToHoursString(int $totalMinutes): string
    {
        $hours = floor($totalMinutes / 60);
        $remainingMinutes = $totalMinutes % 60;

        if ($remainingMinutes > 0) {
            return "{$hours}h {$remainingMinutes}m";
        }

        return "{$hours}h";
    }

    /**
     * Restrict access to Super Admin and Management.
     */
    private function authorizeAccess(Request $request): void
    {
        $user = auth('api')->user() ?? auth()->user();

        if (!$user) {
            abort(401, 'Unauthenticated. Please provide a valid Bearer token.');
        }

        $roleName = strtolower(trim(optional($user->role)->name ?? ''));
        $allowedRoles = [
            'super admin',
            'admin',
            'sub admin',
            'subadmin',
            'management',
            'director',
            'general manager',
            'hr manager',
        ];

        if (in_array($roleName, $allowedRoles)) {
            return;
        }

        if ($user->hasPermission('projects', 'read') || $user->hasPermission('reports', 'read')) {
            return;
        }

        abort(403, 'Forbidden - Access restricted to Super Admin and Management only.');
    }

    /**
     * Authenticate via Bearer token or URL token for export downloads.
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
                // Token parsing failure handled in authorizeAccess
            }
        }
    }
}

