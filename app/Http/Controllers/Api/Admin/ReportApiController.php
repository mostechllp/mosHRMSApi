<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Exports\AttendanceExport;
use App\Exports\LeaveExport;
use App\Exports\EmployeeExport;
use App\Exports\TaskReportExport;
use App\Exports\GenericExport;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use App\Models\TaskReport;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;


class ReportApiController extends ApiController
{
    /**
     * Attendance Report Listing
     */
    public function attendanceReport(Request $request): JsonResponse
    {
        $dateRange = $request->get('date_range', 'today');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');
        $search = $request->get('search');
        $perPage = $request->get('per_page', 500);

        [$startDate, $endDate] = $this->getDateRange(
            $dateRange,
            $request->get('from_date'),
            $request->get('to_date')
        );

        $employeesQuery = Employee::with([
            'user.company',
            'user.department',
            'user.designation'
        ])
            ->whereHas('user', function ($query) {
                $query->where('status', 'active')
                    ->where('type', '!=', 'admin');
            });

        if ($employeeId && $employeeId !== 'all') {
            $employeesQuery->where('id', $employeeId);
        }

        if ($departmentId && $departmentId !== 'all') {
            $employeesQuery->whereHas('user', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            });
        }

        if ($search) {
            $employeesQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $employees = $employeesQuery->get();

        if ($employees->isEmpty()) {
            return $this->success([
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'per_page' => (int) $perPage,
                    'current_page' => 1,
                    'last_page' => 0
                ]
            ]);
        }

        $userIds = $employees->pluck('user_id')->toArray();

        $workingHours = WorkingHour::all()
            ->keyBy(fn($wh) => strtolower($wh->day));

        $allLogs = AttendanceLog::with('breaks')
            ->whereBetween('log_date', [$startDate, $endDate])
            ->whereIn('userid', $userIds)
            ->orderBy('log_date')
            ->get()
            ->groupBy(['log_date', 'userid']);

        $reportData = [];

        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate->lte($lastDate)) {

            $date = $currentDate->toDateString();

            $dayLogs = $allLogs->get($date, collect());

            $dayName = strtolower($currentDate->format('l'));

            $workingHourConfig = $workingHours->get($dayName);

            $standardHours = 8;

            if (
                $workingHourConfig &&
                $workingHourConfig->is_enabled &&
                $workingHourConfig->start_time &&
                $workingHourConfig->end_time
            ) {
                $configStart = Carbon::createFromTimeString($workingHourConfig->start_time);
                $configEnd = Carbon::createFromTimeString($workingHourConfig->end_time);

                $standardHours = round(
                    $configStart->diffInMinutes($configEnd) / 60,
                    2
                );
            }

            foreach ($employees as $employee) {

                $logs = $dayLogs->get($employee->user_id, collect());

                $punchIn = $logs->min('punch_in');
                $punchOut = $logs->max('punch_out');

                $workedHours = 0;
                $overtimeMinutes = 0;

                $status = $punchIn ? 'Present' : 'Absent';

                if ($punchIn && $punchOut) {

                    $workedMinutes = Carbon::parse($punchIn)
                        ->diffInMinutes(Carbon::parse($punchOut));

                    // Sum all break minutes for the day
                    $breakMinutes = $logs->sum(function ($log) {
                        return $log->breaks->sum('duration_minutes');
                    });

                    // Deduct breaks
                    $workedMinutes = max(0, $workedMinutes - $breakMinutes);

                    $workedHours = round($workedMinutes / 60, 2);

                    if ($workedHours >= $standardHours) {
                        $status = 'Full Day';
                    } elseif ($workedHours >= ($standardHours / 2)) {
                        $status = 'Half Day';
                    } else {
                        $status = 'Absent';
                    }

                    $overtimeMinutes = max(
                        0,
                        $workedMinutes - ($standardHours * 60)
                    );
                }

                $reportData[] = [
                    'employee_id' => $employee->employee_id,
                    'name' => trim($employee->first_name . ' ' . $employee->last_name),
                    'department' => $employee->user->department->name ?? 'N/A',
                    'designation' => $employee->user->designation->name ?? 'N/A',
                    'company' => $employee->user->company->name ?? 'N/A',
                    'date' => $date,
                    'punch_in' => $punchIn
                        ? Carbon::parse($punchIn)->format('h:i A')
                        : '-',
                    'punch_out' => $punchOut
                        ? Carbon::parse($punchOut)->format('h:i A')
                        : '-',
                    'worked_hours' => $workedHours,
                    'standard_hours' => $standardHours,
                    'overtime' => $this->formatOvertimeMinutes($overtimeMinutes),
                    'status' => $status,
                ];
            }

            $currentDate->addDay();
        }

        usort($reportData, function ($a, $b) {

            if ($a['date'] === $b['date']) {
                return strcmp($a['employee_id'], $b['employee_id']);
            }

            return strcmp($b['date'], $a['date']);
        });

        $currentPage = (int) $request->get('page', 1);

        $total = count($reportData);

        $paginatedItems = array_slice(
            $reportData,
            ($currentPage - 1) * $perPage,
            $perPage
        );

        return $this->success([
            'data' => array_values($paginatedItems),
            'meta' => [
                'total' => $total,
                'per_page' => (int) $perPage,
                'current_page' => $currentPage,
                'last_page' => (int) ceil($total / $perPage),
            ]
        ]);
    }
    /**
     * Leave Report Listing
     */
    public function leaveReport(Request $request): JsonResponse
    {
        $dateRange = $request->get('date_range', 'this_month');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');
        $status = $request->get('status');
        $perPage = $request->get('per_page', 50);

        [$startDate, $endDate] = $this->getDateRange($dateRange, $request->get('start_date'), $request->get('end_date'));

        $query = LeaveRequest::with(['employee.user.department', 'leaveType'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate]);
            });

        if ($employeeId && $employeeId !== 'all') {
            $query->whereHas('employee', function ($q) use ($employeeId) {
                $q->where('id', $employeeId);
            });
        }

        if ($departmentId && $departmentId !== 'all') {
            $query->whereHas('employee.user', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $leaves = $query->latest()->paginate($perPage);

        return $this->success($leaves);
    }

    /**
     * Employee Report Listing
     */
    public function employeeReport(Request $request): JsonResponse
    {
        $departmentId = $request->get('department_id');
        $companyId = $request->get('company_id');
        $perPage = $request->get('per_page', 10);

        $query = Employee::with(['user.department', 'user.designation', 'user.company']);

        if ($departmentId && $departmentId !== 'all') {
            $query->whereHas('user', function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if ($companyId && $companyId !== 'all') {
            $query->whereHas('user', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        $employees = $query->paginate($perPage);

        return $this->success($employees);
    }

    /**
     * Employee Details Report
     */
    public function employeeDetails(): JsonResponse
    {
        $employees = Employee::select(
            'id',
            'employee_id',
            'first_name',
            'last_name',
            'user_id',
            'joining_date',
            'dob',
            'aadhar_number',
            'pan_number',
            'company_email',
            'personal_email',
            'personal_number'
        )
            ->whereHas('user', function ($query) {
                $query->where('status', 'active')
                    ->where('type', '!=', 'admin');
            })
            ->with([
                'user:id,company_id,department_id,designation_id,email,status,type',
                'user.company:id,company_name',
                'user.department:id,name',
                'user.designation:id,name'
            ])
            ->get();

        return $this->success($employees);
    }

    /**
     * Employee Nearest Expiry (within 30 days)
     */
    public function employeeNearestExpiry(): JsonResponse
    {
        $threshold = Carbon::now()->addDays(30);
        $employees = Employee::where(function ($query) use ($threshold) {
            $query->whereDate('passport_expiry_date', '<=', $threshold)
                ->orWhereDate('visa_expiry_date', '<=', $threshold)
                ->orWhereDate('labor_expiry_date', '<=', $threshold)
                ->orWhereDate('eid_expiry_date', '<=', $threshold);
        })->get();

        return $this->success([
            'employees' => $employees,
            'title' => 'Employee Nearest Expiry Details',
            'subtitle' => 'Expiring within 30 days'
        ]);
    }

    /**
     * Employee Upcoming Renewals (31-90 days)
     */
    public function employeeUpcomingRenewals(): JsonResponse
    {
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);

        $employees = Employee::where(function ($query) use ($start, $end) {
            $query->whereBetween('passport_expiry_date', [$start, $end])
                ->orWhereBetween('visa_expiry_date', [$start, $end])
                ->orWhereBetween('labor_expiry_date', [$start, $end])
                ->orWhereBetween('eid_expiry_date', [$start, $end]);
        })->get();

        return $this->success([
            'employees' => $employees,
            'title' => 'Employee Upcoming Renewals',
            'subtitle' => 'Expiring within 31-90 days'
        ]);
    }

    /**
     * Company Nearest Expiry (within 30 days)
     */
    public function companyNearestExpiry(): JsonResponse
    {
        $threshold = Carbon::now()->addDays(30);
        $companies = Company::where(function ($query) use ($threshold) {
            $query->whereDate('trade_license_expiry', '<=', $threshold)
                ->orWhereDate('establishment_card_expiry', '<=', $threshold);
        })->get();

        return $this->success([
            'companies' => $companies,
            'title' => 'Company Nearest Expiry Details',
            'subtitle' => 'Expiring within 30 days'
        ]);
    }

    /**
     * Company Upcoming Renewals (31-90 days)
     */
    public function companyUpcomingRenewals(): JsonResponse
    {
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);

        $companies = Company::where(function ($query) use ($start, $end) {
            $query->whereBetween('trade_license_expiry', [$start, $end])
                ->orWhereBetween('establishment_card_expiry', [$start, $end]);
        })->get();

        return $this->success([
            'companies' => $companies,
            'title' => 'Company Upcoming Renewals',
            'subtitle' => 'Expiring within 31-90 days'
        ]);
    }

    /**
     * Pending Leave Requests Report
     */
    public function pendingLeavesReport(): JsonResponse
    {
        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success([
            'leaves' => $leaves,
            'title' => 'Employees Pending Leave Reports'
        ]);
    }

    /**
     * Export Report
     */
    /**
     * Main Export Dispatcher
     */
    public function export(Request $request)
    {
        $this->authenticateFromToken($request);

        $reportType = $request->get('report_type');

        return match ($reportType) {
            'attendance' => $this->attendanceExport($request),
            'leaves' => $this->leaveExport($request),
            'employee-details' => $this->employeeExport($request),
            'task_report' => $this->taskReportExport($request),
            'company-expiry' => $this->companyNearestExpiryExport($request),
            'company-upcoming-renewals' => $this->companyUpcomingRenewalsExport($request),
            'employee-nearest-expiry' => $this->employeeNearestExpiryExport($request),
            'employee-upcoming-renewals' => $this->employeeUpcomingRenewalsExport($request),
            'pending-leaves' => $this->pendingLeavesReportExport($request),
            default => $this->error('Invalid report type', 400),
        };
    }

    public function attendanceExport(Request $request)
    {
        $this->authenticateFromToken($request);

        $dateRange = $request->get('date_range', 'today');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');
        $search = $request->get('search');

        [$startDate, $endDate] = $this->getDateRange(
            $dateRange,
            $request->get('from_date'),
            $request->get('to_date')
        );

        $employeesQuery = Employee::with([
            'user.company',
            'user.department',
            'user.designation'
        ])
            ->whereHas('user', function ($query) {
                $query->where('status', 'active')
                    ->where('type', '!=', 'admin');
            });

        if ($employeeId && $employeeId !== 'all') {
            $employeesQuery->where('id', $employeeId);
        }

        if ($departmentId && $departmentId !== 'all') {
            $employeesQuery->whereHas('user', function ($query) use ($departmentId) {
                $query->where('department_id', $departmentId);
            });
        }

        if ($search) {
            $employeesQuery->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $employees = $employeesQuery->get();

        $userIds = $employees->pluck('user_id')->toArray();

        $workingHours = WorkingHour::all()
            ->keyBy(fn($item) => strtolower($item->day));

        $allLogs = AttendanceLog::whereBetween('log_date', [$startDate, $endDate])
            ->whereIn('userid', $userIds)
            ->orderBy('log_date')
            ->get()
            ->groupBy(['log_date', 'userid']);

        $data = [];

        $currentDate = Carbon::parse($startDate);
        $lastDate = Carbon::parse($endDate);

        while ($currentDate->lte($lastDate)) {

            $date = $currentDate->toDateString();
            $dayLogs = $allLogs->get($date, collect());

            $dayName = strtolower($currentDate->format('l'));

            $workingHourConfig = $workingHours->get($dayName);

            $standardHours = 8;

            if (
                $workingHourConfig &&
                $workingHourConfig->is_enabled &&
                $workingHourConfig->start_time &&
                $workingHourConfig->end_time
            ) {
                $start = Carbon::createFromTimeString($workingHourConfig->start_time);
                $end = Carbon::createFromTimeString($workingHourConfig->end_time);

                $standardHours = round(
                    $start->diffInMinutes($end) / 60,
                    2
                );
            }

            foreach ($employees as $employee) {

                $logs = $dayLogs->get($employee->user_id, collect());

                $punchIn = $logs->min('punch_in');
                $punchOut = $logs->max('punch_out');

                $workedHours = 0;
                $overtimeMinutes = 0;

                $status = $punchIn ? 'Present' : 'Absent';

                if ($punchIn && $punchOut) {

                    $workedMinutes = Carbon::parse($punchIn)
                        ->diffInMinutes(Carbon::parse($punchOut));

                    // Total break minutes
                    $breakMinutes = $logs->sum(function ($log) {
                        return $log->breaks->sum('duration_minutes');
                    });

                    // Deduct break duration
                    $workedMinutes = max(0, $workedMinutes - $breakMinutes);

                    $workedHours = round($workedMinutes / 60, 2);

                    if ($workedHours >= $standardHours) {
                        $status = 'Full Day';
                    } elseif ($workedHours >= ($standardHours / 2)) {
                        $status = 'Half Day';
                    } else {
                        $status = 'Absent';
                    }

                    $overtimeMinutes = max(
                        0,
                        $workedMinutes - ($standardHours * 60)
                    );
                }

                $data[] = [
                    $employee->employee_id,
                    trim($employee->first_name . ' ' . $employee->last_name),
                    $employee->user->department->name ?? 'N/A',
                    $employee->user->designation->name ?? 'N/A',
                    $employee->user->company->name ?? 'N/A',
                    $date,
                    $punchIn ? Carbon::parse($punchIn)->format('h:i A') : '-',
                    $punchOut ? Carbon::parse($punchOut)->format('h:i A') : '-',
                    $this->formatWorkedHours($workedHours),
                    $standardHours,
                    $this->formatOvertimeMinutes($overtimeMinutes),
                    $status,
                ];
            }

            $currentDate->addDay();
        }

        usort($data, function ($a, $b) {
            if ($a[5] == $b[5]) {
                return strcmp($a[0], $b[0]);
            }

            return strcmp($b[5], $a[5]);
        });

        return $this->downloadResponse(
            new AttendanceExport($data),
            "attendance_report",
            $request->get('format')
        );
    }

    private function formatWorkedHours($hours): string
{
    if ($hours <= 0) {
        return '--';
    }

    $totalMinutes = (int) round($hours * 60);

    $hrs = intdiv($totalMinutes, 60);
    $mins = $totalMinutes % 60;

    if ($hrs == 0) {
        return "{$mins} mins";
    }

    if ($mins == 0) {
        return "{$hrs} hrs";
    }

    return "{$hrs} hrs {$mins} mins";
}

    public function leaveExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $dateRange = $request->get('date_range', 'this_month');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');
        $status = $request->get('status');

        [$startDate, $endDate] = $this->getDateRange($dateRange, $request->get('start_date'), $request->get('end_date'));

        $query = LeaveRequest::with(['employee.user', 'leaveType'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])->orWhereBetween('end_date', [$startDate, $endDate]);
            });

        if ($employeeId && $employeeId !== 'all')
            $query->whereHas('employee', fn($q) => $q->where('id', $employeeId));
        if ($departmentId && $departmentId !== 'all')
            $query->whereHas('employee.user', fn($q) => $q->where('department_id', $departmentId));
        if ($status)
            $query->where('status', $status);

        $data = [];
        $sessionMap = [
            'morning' => 'M',
            'afternoon' => 'A',
        ];

        foreach ($query->get() as $leave) {
            $session1 = $sessionMap[$leave->session1] ?? 'N/A';
            $session2 = $sessionMap[$leave->session2] ?? 'N/A';

            $data[] = [$leave->employee->employee_id ?? 'N/A', ($leave->employee->first_name ?? '') . ' ' . ($leave->employee->last_name ?? ''), $leave->leaveType->name ?? 'N/A', $leave->start_date->toDateString(), $leave->end_date->toDateString(), $session1 . ' - ' . $session2, $leave->duration_days, ucfirst($leave->status), $leave->reason];
        }

        return $this->downloadResponse(new LeaveExport($data), "leave_report", $request->get('format'));
    }

    public function employeeExport(Request $request)
    {
        $this->authenticateFromToken($request);

        $departmentId = $request->get('department_id');
        $companyId = $request->get('company_id');

        $query = Employee::with(['user.company', 'user.department', 'user.designation'])
            ->whereHas('user', function ($q) {
                $q->where('status', 'active')
                    ->where('type', '!=', 'admin');
            });

        if ($departmentId && $departmentId !== 'all') {
            $query->whereRelation('user', 'department_id', $departmentId);
        }

        if ($companyId && $companyId !== 'all') {
            $query->whereRelation('user', 'company_id', $companyId);
        }

        $data = [];

        foreach ($query->get() as $emp) {
            $data[] = [
                $emp->employee_id,
                $emp->first_name . ' ' . $emp->last_name,
                $emp->user->company->company_name ?? 'N/A',
                $emp->user->department->name ?? 'N/A',
                $emp->user->designation->name ?? 'N/A',
                $emp->joining_date,
                $emp->dob,
                $emp->company_email,
                $emp->personal_email,
                $emp->personal_number,
                $emp->aadhar_number,
                $emp->pan_number,
                ucfirst($emp->user->status),
            ];
        }

        return $this->downloadResponse(
            new EmployeeExport($data),
            "employee_report",
            $request->get('format')
        );
    }

    /**
     * Task Report Listing — with date in dd/MM/yyyy, employee name, and task fields.
     * Searchable by date range (from_date / to_date, or date_range preset) and employee name.
     */
    public function taskReport(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $dateRange = $request->get('date_range');
        $search = $request->get('search'); // employee name or id

        $query = TaskReport::with(['user.employee']);

        // Date range filtering
        if ($fromDate && $toDate) {
            // Accept dd/MM/yyyy or Y-m-d
            try {
                $start = strlen($fromDate) === 10 && substr($fromDate, 2, 1) === '/'
                    ? Carbon::createFromFormat('d/m/Y', $fromDate)->toDateString()
                    : Carbon::parse($fromDate)->toDateString();
                $end = strlen($toDate) === 10 && substr($toDate, 2, 1) === '/'
                    ? Carbon::createFromFormat('d/m/Y', $toDate)->toDateString()
                    : Carbon::parse($toDate)->toDateString();
                $query->whereBetween('date', [$start, $end]);
            } catch (\Exception $e) {
                // ignore invalid date; fall through
            }
        } elseif ($dateRange) {
            [$startDate, $endDate] = $this->getDateRange($dateRange);
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        // Search by employee name or employee ID string
        if ($search) {
            $query->whereHas('user.employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $reports = $query->latest('date')->paginate($perPage);

        // Transform to the required output shape
        $transformed = $reports->getCollection()->map(function ($report) {
            $employee = $report->user->employee ?? null;
            return [
                'id' => $report->id,
                'report_date' => $report->date
                    ? Carbon::parse($report->date)->format('d/m/Y')
                    : null,
                'employee_id' => $employee->employee_id ?? 'N/A',
                'employee_name' => $employee
                    ? trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''))
                    : ($report->user->username ?? 'N/A'),
                'tasks_completed' => $report->tasks_completed,
                'pending_tasks' => $report->pending_tasks,
                'plan_tomorrow' => $report->plan_tomorrow,
                'remarks' => $report->remarks,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at,
            ];
        });

        return $this->success([
            'data' => $transformed,
            'meta' => [
                'total' => $reports->total(),
                'per_page' => $reports->perPage(),
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
            ],
        ]);
    }

    public function taskReportExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $format = strtolower($request->get('format', 'csv'));
        $dateRange = $request->get('date_range', 'today');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');
        $search = $request->get('search');

        $taskQuery = TaskReport::with(['user.employee']);

        // Resolve date range
        $periodLabel = null;
        if ($fromDate && $toDate) {
            try {
                $start = strlen($fromDate) === 10 && substr($fromDate, 2, 1) === '/'
                    ? Carbon::createFromFormat('d/m/Y', $fromDate)->toDateString()
                    : Carbon::parse($fromDate)->toDateString();
                $end = strlen($toDate) === 10 && substr($toDate, 2, 1) === '/'
                    ? Carbon::createFromFormat('d/m/Y', $toDate)->toDateString()
                    : Carbon::parse($toDate)->toDateString();
                $taskQuery->whereBetween('date', [$start, $end]);
                $periodLabel = $start . ' to ' . $end;
            } catch (\Exception $e) {
                // fall through
            }
        } else {
            [$startDate, $endDate] = $this->getDateRange($dateRange, $fromDate, $toDate);
            $taskQuery->whereBetween('date', [$startDate, $endDate]);
            $periodLabel = $startDate . ' to ' . $endDate;
        }

        // Search by employee name
        if ($search) {
            $taskQuery->whereHas('user.employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $records = $taskQuery->latest('date')->get();

        // ── PDF: use dedicated styled template ──
        if ($format === 'pdf') {
            $rows = $records->map(function ($report) {
                $employee = $report->user->employee ?? null;
                return [
                    'date' => $report->date ? Carbon::parse($report->date)->format('d/m/Y') : '-',
                    'employee_name' => $employee
                        ? trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''))
                        : ($report->user->username ?? 'N/A'),
                    'tasks_completed' => str_replace("\t", "    ", $report->tasks_completed ?? ''),
                    'pending_tasks' => str_replace("\t", "    ", $report->pending_tasks ?? ''),
                    'plan_tomorrow' => str_replace("\t", "    ", $report->plan_tomorrow ?? ''),
                    'remarks' => str_replace("\t", "    ", $report->remarks ?? ''),
                ];
            })->values()->toArray();

            $withRemarks = collect($rows)->filter(fn($r) => !empty($r['remarks']))->count();

            $pdf = Pdf::loadView('reports.task_report_pdf', [
                'rows' => $rows,
                'period' => $periodLabel,
                'total' => count($rows),
                'withRemarks' => $withRemarks,
            ])->setPaper('a4', 'landscape');

            $filename = 'task_report_' . now()->format('YmdHis') . '.pdf';
            return $pdf->download($filename);
        }

        // ── Excel / CSV ──
        $flatData = [];
        foreach ($records as $report) {
            $employee = $report->user->employee ?? null;
            $flatData[] = [
                $report->date ? Carbon::parse($report->date)->format('d/m/Y') : 'N/A',
                $employee->employee_id ?? 'N/A',
                $employee
                    ? trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''))
                    : ($report->user->username ?? 'N/A'),
                $report->tasks_completed ?? '',
                $report->pending_tasks ?? '',
                $report->plan_tomorrow ?? '',
                $report->remarks ?? '',
            ];
        }

        $filename = 'task_report_' . now()->format('YmdHis');
        $exportObj = new TaskReportExport($flatData);

        // Clear all output buffers to prevent corrupted XLSX files
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return match ($format) {
            'xlsx' => Excel::download($exportObj, $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX),
            default => Excel::download($exportObj, $filename . '.csv', \Maatwebsite\Excel\Excel::CSV),
        };
    }

    public function pendingLeavesReportExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $dateRange = $request->get('date_range', 'this_month');
        $employeeId = $request->get('employee_id');
        $departmentId = $request->get('department_id');

        [$startDate, $endDate] = $this->getDateRange($dateRange, $request->get('start_date'), $request->get('end_date'));

        $query = LeaveRequest::with(['employee.user', 'leaveType'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])->orWhereBetween('end_date', [$startDate, $endDate]);
            });

        if ($employeeId && $employeeId !== 'all')
            $query->whereHas('employee', fn($q) => $q->where('id', $employeeId));
        if ($departmentId && $departmentId !== 'all')
            $query->whereHas('employee.user', fn($q) => $q->where('department_id', $departmentId));

        $query->where('status', 'pending');

        $data = [];
        $sessionMap = [
            'morning' => 'M',
            'afternoon' => 'A',
        ];

        foreach ($query->get() as $leave) {
            $session1 = $sessionMap[$leave->session1] ?? 'N/A';
            $session2 = $sessionMap[$leave->session2] ?? 'N/A';
            $data[] = [$leave->employee->employee_id ?? 'N/A', ($leave->employee->first_name ?? '') . ' ' . ($leave->employee->last_name ?? ''), $leave->leaveType->name ?? 'N/A', $leave->start_date->toDateString(), $leave->end_date->toDateString(), $session1 . ' - ' . $session2, $leave->duration_days,  ucfirst($leave->status), $leave->reason];
        }

        return $this->downloadResponse(new LeaveExport($data), "pending_leave_request_report", $request->get('format'));
    }

    public function companyNearestExpiryExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $threshold = Carbon::now()->addDays(30);
        $companies = Company::where(function ($query) use ($threshold) {
            $query->whereDate('trade_license_expiry', '<=', $threshold)
                ->orWhereDate('establishment_card_expiry', '<=', $threshold);
        })->get();

        $headings = [
            'Company Name',
            'Trade License',
            'Trade License Expiry',
            'Establishment Card Expiry',
        ];

        $data = [];
        foreach ($companies as $company) {
            $data[] = [
                $company->company_name ?? 'N/A',
                $company->trade_license ?? 'N/A',
                $company->trade_license_expiry ?? 'N/A',
                $company->establishment_card_expiry ?? 'N/A',
            ];
        }

        return $this->downloadResponse(
            new GenericExport($data, $headings),
            "company_nearest_expiry_report",
            $request->get('format')
        );
    }

    public function companyUpcomingRenewalsExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);

        $companies = Company::where(function ($query) use ($start, $end) {
            $query->whereBetween('trade_license_expiry', [$start, $end])
                ->orWhereBetween('establishment_card_expiry', [$start, $end]);
        })->get();

        $headings = [
            'Company Name',
            'Trade License',
            'Trade License Expiry',
            'Establishment Card Expiry',
        ];

        $data = [];
        foreach ($companies as $company) {
            $data[] = [
                $company->company_name ?? 'N/A',
                $company->trade_license ?? 'N/A',
                $company->trade_license_expiry ?? 'N/A',
                $company->establishment_card_expiry ?? 'N/A',
            ];
        }

        return $this->downloadResponse(
            new GenericExport($data, $headings),
            "company_upcoming_renewals_report",
            $request->get('format')
        );
    }

    public function employeeNearestExpiryExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $threshold = Carbon::now()->addDays(30);
        $employees = Employee::with(['user.company', 'user.department', 'user.designation'])
            ->where(function ($query) use ($threshold) {
                $query->whereDate('passport_expiry_date', '<=', $threshold)
                    ->orWhereDate('visa_expiry_date', '<=', $threshold)
                    ->orWhereDate('labor_expiry_date', '<=', $threshold)
                    ->orWhereDate('eid_expiry_date', '<=', $threshold);
            })->get();

        $headings = [
            'Employee ID',
            'Name',
            'Company',
            'Department',
            'Designation',
            'Passport Expiry',
            'Visa Expiry',
            'Labor Card Expiry',
            'EID Expiry',
        ];

        $data = [];
        foreach ($employees as $emp) {
            $data[] = [
                $emp->employee_id,
                $emp->first_name . ' ' . $emp->last_name,
                $emp->user->company->company_name ?? 'N/A',
                $emp->user->department->name ?? 'N/A',
                $emp->user->designation->name ?? 'N/A',
                $emp->passport_expiry_date ?? 'N/A',
                $emp->visa_expiry_date ?? 'N/A',
                $emp->labor_expiry_date ?? 'N/A',
                $emp->eid_expiry_date ?? 'N/A',
            ];
        }

        return $this->downloadResponse(
            new GenericExport($data, $headings),
            "employee_nearest_expiry_report",
            $request->get('format')
        );
    }

    public function employeeUpcomingRenewalsExport(Request $request)
    {
        $this->authenticateFromToken($request);
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);

        $employees = Employee::with(['user.company', 'user.department', 'user.designation'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('passport_expiry_date', [$start, $end])
                    ->orWhereBetween('visa_expiry_date', [$start, $end])
                    ->orWhereBetween('labor_expiry_date', [$start, $end])
                    ->orWhereBetween('eid_expiry_date', [$start, $end]);
            })->get();

        $headings = [
            'Employee ID',
            'Name',
            'Company',
            'Department',
            'Designation',
            'Passport Expiry',
            'Visa Expiry',
            'Labor Card Expiry',
            'EID Expiry',
        ];

        $data = [];
        foreach ($employees as $emp) {
            $data[] = [
                $emp->employee_id,
                $emp->first_name . ' ' . $emp->last_name,
                $emp->user->company->company_name ?? 'N/A',
                $emp->user->department->name ?? 'N/A',
                $emp->user->designation->name ?? 'N/A',
                $emp->passport_expiry_date ?? 'N/A',
                $emp->visa_expiry_date ?? 'N/A',
                $emp->labor_expiry_date ?? 'N/A',
                $emp->eid_expiry_date ?? 'N/A',
            ];
        }

        return $this->downloadResponse(
            new GenericExport($data, $headings),
            "employee_upcoming_renewals_report",
            $request->get('format')
        );
    }



    /**
     * Helper: Handle authentication via query token for downloads
     */
    private function authenticateFromToken(Request $request)
    {
        if (!$request->bearerToken() && $request->has('token')) {
            try {
                $user = auth('api')->setToken($request->token)->user();
                if ($user) {
                    auth('api')->setUser($user);
                }
            } catch (\Exception $e) {
                // Fail silently or handle
            }
        }

        if (!auth('api')->check()) {
            abort(403, 'Forbidden - Access denied. Please provide a valid token.');
        }
    }

    /**
     * Helper: Centralized download response handler
     */
    private function downloadResponse($exportClass, $baseFilename, $format)
    {
        // Clear all output buffers to prevent corrupted XLSX files
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = $baseFilename . "_" . now()->format('YmdHis');
        $format = strtolower($format);

        if ($format === 'pdf') {
            $data = method_exists($exportClass, 'array') ? $exportClass->array() : (method_exists($exportClass, 'collection') ? $exportClass->collection()->toArray() : []);
            $headings = method_exists($exportClass, 'headings') ? $exportClass->headings() : [];
            $title = method_exists($exportClass, 'title') ? $exportClass->title() : str_replace('_', ' ', ucfirst($baseFilename));

            $pdf = Pdf::loadView('reports.generic_pdf', [
                'data' => $data,
                'headings' => $headings,
                'title' => $title
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename . '.pdf');
        }

        return match ($format) {
            'xlsx' => Excel::download($exportClass, $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX),
            default => Excel::download($exportClass, $filename . '.csv', \Maatwebsite\Excel\Excel::CSV),
        };
    }

    /**
     * Helper: Get Date Range Group
     */
    private function getDateRange($preset, $from = null, $to = null): array
    {
        $now = Carbon::now();
        switch ($preset) {
            case 'today':
                return [$now->copy()->toDateString(), $now->copy()->toDateString()];
            case 'yesterday':
                $y = $now->copy()->subDay()->toDateString();
                return [$y, $y];
            case 'this_week':
                return [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()];
            case 'this_month':
                return [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()];
            case 'custom':
                if ($from && $to) {
                    return [
                        Carbon::createFromFormat('Y-m-d', $from)->toDateString(),
                        Carbon::createFromFormat('Y-m-d', $to)->toDateString(),
                    ];
                }
                return [$now->copy()->toDateString(), $now->copy()->toDateString()];
            default:
                return [$now->copy()->toDateString(), $now->copy()->toDateString()];
        }
    }

    /**
     * Helper: Format overtime minutes into a human-readable string.
     * Examples: 0 => '-', 30 => '30 mins', 60 => '1 hour', 75 => '1 hour 15 mins'
     */
    private function formatOvertimeMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return '-';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            $hourLabel = $hours === 1 ? '1 hour' : "{$hours} hours";
            return "{$hourLabel} {$mins} mins";
        }

        if ($hours > 0) {
            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return "{$mins} mins";
    }
}
