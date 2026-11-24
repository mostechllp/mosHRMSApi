<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\Document;
use App\Models\User;
use App\Models\Party;
use App\Models\Task;
use App\Models\Project;
use App\Models\Folder;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class DashboardApiController extends ApiController
{
    /**
     * Get main dashboard statistics and data.
     */
    public function index(Request $request): JsonResponse
    {
        // Documents
        $hr = Document::with('folder')
            ->where('type', 'hr')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($doc) {
                $doc->folder_name = $doc->folder?->name;
                $doc->shared_users = User::whereIn('id', $doc->share_with ?? [])->get();
                return $doc;
            });
    
        $agreements = Document::with('folder')
            ->where('type', 'agreements')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($doc) {
                $doc->folder_name = $doc->folder?->name;
                $doc->shared_users = User::whereIn('id', $doc->share_with ?? [])->get();
                return $doc;
            });
    
        $others = Document::with('folder')
            ->where('type', 'others')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($doc) {
                $doc->folder_name = $doc->folder?->name;
                $doc->shared_users = User::whereIn('id', $doc->share_with ?? [])->get();
                return $doc;
            });
    
        $organization_files = Document::with('folder')
            ->where('type', 'organization')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($doc) {
                $doc->folder_name = $doc->folder?->name;
                $doc->shared_users = User::whereIn('id', $doc->share_with ?? [])->get();
                return $doc;
            });
    
        $tasks = Task::get();
        $projects = Project::get();
    
        $folders = Folder::all();
    
        $share_with = User::with(['employee', 'company', 'department'])
            ->select('id', 'username')
            ->get();
    
        $parties = Party::select('id', 'name')->get();
    
        // Employees
        $employees = Employee::with([
            'user.department',
            'user.designation',
            'user.company'
        ])
            ->whereHas('user', function ($query) {
                $query->where('type', '!=', 'admin')
                    ->where('status', 'active');
            })
            ->get();
    
        // Date range: Today to next 6 months
        $today = Carbon::today();
        $sixMonthsLater = $today->copy()->addMonths(6);
    
        $yesterday = Carbon::yesterday();
    
        // Attendance
        $todayStats = $this->getAttendanceStatsByDate($today);
        $yesterdayStats = $this->getAttendanceStatsByDate($yesterday);
    
        $punchChartData = [
            'today' => [
                'punched_in' => $todayStats['punched_in'],
                'punched_out' => $todayStats['punched_out'],
            ],
            'yesterday' => [
                'punched_in' => $yesterdayStats['punched_in'],
                'punched_out' => $yesterdayStats['punched_out'],
            ]
        ];
    
        $weeklyData = [];
        $weeklyLabels = [];
    
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
    
            $count = AttendanceLog::whereDate('log_date', $date)
                ->distinct('userid')
                ->count('userid');
    
            $weeklyData[] = $count;
            $weeklyLabels[] = $date->format('D');
        }
    
        // Today's approved leaves
        $todayLeaves = LeaveRequest::with([
            'employee.user.department',
            'employee.user.designation',
            'leaveType'
        ])
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get()
            ->map(function ($leave) {
                $employee = $leave->employee;
                $user = $employee?->user;
    
                $employeeName = $employee
                    ? trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''))
                    : null;
    
                if (empty($employeeName) && $user) {
                    $employeeName = $user->username;
                }
    
                return [
                    'id' => $leave->id,
                    'employee_id' => $leave->employee_id,
                    'employee_name' => $employeeName,
                    'department_name' => $user?->department?->name ?? null,
                    'designation' => $user?->designation?->name ?? null,
                    'leave_type' => $leave->leaveType?->name ?? null,
                    'start_date' => $leave->start_date
                        ? (is_string($leave->start_date)
                            ? $leave->start_date
                            : $leave->start_date->format('Y-m-d'))
                        : null,
                    'end_date' => $leave->end_date
                        ? (is_string($leave->end_date)
                            ? $leave->end_date
                            : $leave->end_date->format('Y-m-d'))
                        : null,
                    'duration_days' => $leave->duration_days,
                    'reason' => $leave->reason,
                    'status' => $leave->status,
                    'session1' => $leave->session1,
                    'session2' => $leave->session2,
                ];
            });
    
        // Birthday and special day lists
        $todayMonth = $today->month;
        $todayDay = $today->day;
    
        $birthdaysList = [];
        $specialDaysList = [];
        $todayBirthdays = [];
        $todaySpecialDays = [];
    
        foreach ($employees as $employee) {
    
            $user = $employee->user;
    
            $employeeName = trim(
                ($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')
            );
    
            if (empty($employeeName) && $user) {
                $employeeName = $user->username;
            }
    
            $deptName = $user?->department?->name ?? null;
            $designationName = $user?->designation?->name ?? null;
    
            /*
            |--------------------------------------------------------------------------
            | 1. Birthdays
            |--------------------------------------------------------------------------
            */
    
            if (!empty($employee->dob)) {
                try {
                    $dob = Carbon::parse($employee->dob);
    
                    // Birthday in the current year
                    $bdayThisYear = Carbon::create(
                        $today->year,
                        $dob->month,
                        $dob->day
                    )->startOfDay();
    
                    // If birthday has passed, calculate next year's birthday
                    if ($bdayThisYear->lt($today)) {
                        $bdayThisYear->addYear();
                    }
    
                    // Include only birthdays within the next 6 months
                    if (
                        $bdayThisYear->gte($today) &&
                        $bdayThisYear->lte($sixMonthsLater)
                    ) {
                        $isToday = (
                            $dob->month === $todayMonth &&
                            $dob->day === $todayDay
                        );
    
                        $daysUntil = (int) $today->diffInDays(
                            $bdayThisYear,
                            false
                        );
    
                        $bdayItem = [
                            'id' => $employee->id,
                            'employee_id' => $employee->employee_id,
                            'employee_name' => $employeeName,
                            'department_name' => $deptName,
                            'designation' => $designationName,
                            'dob' => $dob->format('Y-m-d'),
                            'date' => $bdayThisYear->format('Y-m-d'),
                            'next_date' => $bdayThisYear->format('Y-m-d'),
                            'is_today' => $isToday,
                            'days_until' => $daysUntil,
                            'type' => 'Birthday',
                            'event_name' => "{$employeeName}'s Birthday",
                        ];
    
                        $birthdaysList[] = $bdayItem;
    
                        if ($isToday) {
                            $todayBirthdays[] = $bdayItem;
                        }
                    }
    
                } catch (\Exception $e) {
                    // Ignore invalid DOB
                }
            }
    
            /*
            |--------------------------------------------------------------------------
            | 2. Work Anniversaries
            |--------------------------------------------------------------------------
            */
    
            if (!empty($employee->joining_date)) {
                try {
                    $joiningDate = Carbon::parse($employee->joining_date);
    
                    // Only employees who joined in a previous year
                    if ($joiningDate->year < $today->year) {
    
                        $annivThisYear = Carbon::create(
                            $today->year,
                            $joiningDate->month,
                            $joiningDate->day
                        )->startOfDay();
    
                        // If anniversary has passed, calculate next year's date
                        if ($annivThisYear->lt($today)) {
                            $annivThisYear->addYear();
                        }
    
                        // Include only anniversaries within the next 6 months
                        if (
                            $annivThisYear->gte($today) &&
                            $annivThisYear->lte($sixMonthsLater)
                        ) {
                            $isToday = (
                                $joiningDate->month === $todayMonth &&
                                $joiningDate->day === $todayDay
                            );
    
                            $years = $annivThisYear->year - $joiningDate->year;
    
                            $daysUntil = (int) $today->diffInDays(
                                $annivThisYear,
                                false
                            );
    
                            $eventName = "{$years} Year Work Anniversary";
    
                            $annivItem = [
                                'id' => $employee->id,
                                'employee_id' => $employee->employee_id,
                                'employee_name' => $employeeName,
                                'department_name' => $deptName,
                                'designation' => $designationName,
                                'type' => 'Work Anniversary',
                                'event_name' => "{$employeeName}'s {$eventName}",
                                'date' => $annivThisYear->format('Y-m-d'),
                                'is_today' => $isToday,
                                'days_until' => $daysUntil,
                                'years' => $years,
                            ];
    
                            $specialDaysList[] = $annivItem;
    
                            if ($isToday) {
                                $todaySpecialDays[] = $annivItem;
                            }
                        }
                    }
    
                } catch (\Exception $e) {
                    // Ignore invalid joining date
                }
            }
    
            /*
            |--------------------------------------------------------------------------
            | 3. Custom Special Days
            |--------------------------------------------------------------------------
            */
    
            if (
                !empty($employee->special_days) &&
                is_array($employee->special_days)
            ) {
    
                // DOB and joining date for duplicate checking
                $dobMonthDay = null;
                $joiningMonthDay = null;
    
                try {
                    if (!empty($employee->dob)) {
                        $dobMonthDay = Carbon::parse($employee->dob)->format('m-d');
                    }
    
                    if (!empty($employee->joining_date)) {
                        $joiningMonthDay = Carbon::parse($employee->joining_date)->format('m-d');
                    }
    
                } catch (\Exception $e) {
                    // Ignore invalid dates
                }
    
                // Avoid duplicate birthday and anniversary entries
                $skipNameKeywords = [
                    'birthday',
                    'birth day',
                    'bday',
                    'anniversary',
                    'work anniversary',
                    'joining'
                ];
    
                foreach ($employee->special_days as $sDay) {
    
                    $sDateStr = is_array($sDay)
                        ? ($sDay['date'] ?? null)
                        : null;
    
                    $sName = is_array($sDay)
                        ? ($sDay['name'] ?? 'Special Day')
                        : 'Special Day';
    
                    $sNameLow = strtolower($sName);
    
                    // Skip names containing birthday or anniversary keywords
                    $nameMatches = false;
    
                    foreach ($skipNameKeywords as $kw) {
                        if (str_contains($sNameLow, $kw)) {
                            $nameMatches = true;
                            break;
                        }
                    }
    
                    if ($nameMatches) {
                        continue;
                    }
    
                    if ($sDateStr) {
                        try {
                            $sDate = Carbon::parse($sDateStr);
    
                            // Calculate this year's special day
                            $sThisYear = Carbon::create(
                                $today->year,
                                $sDate->month,
                                $sDate->day
                            )->startOfDay();
    
                            // If it has passed, calculate next year's date
                            if ($sThisYear->lt($today)) {
                                $sThisYear->addYear();
                            }
    
                            // Include only special days within the next 6 months
                            if (
                                $sThisYear->lt($today) ||
                                $sThisYear->gt($sixMonthsLater)
                            ) {
                                continue;
                            }
    
                            $sMonthDay = $sDate->format('m-d');
    
                            // Skip if date matches DOB or joining date
                            if (
                                $sMonthDay === $dobMonthDay ||
                                $sMonthDay === $joiningMonthDay
                            ) {
                                continue;
                            }
    
                            $isToday = (
                                $sThisYear->format('Y-m-d') === $today->format('Y-m-d')
                            );
    
                            $daysUntil = (int) $today->diffInDays(
                                $sThisYear,
                                false
                            );
    
                            $customItem = [
                                'id' => $employee->id,
                                'employee_id' => $employee->employee_id,
                                'employee_name' => $employeeName,
                                'department_name' => $deptName,
                                'designation' => $designationName,
                                'type' => 'Custom',
                                'event_name' => "{$employeeName}'s {$sName}",
                                'date' => $sThisYear->format('Y-m-d'),
                                'is_today' => $isToday,
                                'days_until' => $daysUntil,
                            ];
    
                            $specialDaysList[] = $customItem;
    
                            if ($isToday) {
                                $todaySpecialDays[] = $customItem;
                            }
    
                        } catch (\Exception $e) {
                            // Ignore invalid special day date
                        }
                    }
                }
            }
        }
    
        /*
        |--------------------------------------------------------------------------
        | Sort Events
        |--------------------------------------------------------------------------
        */
    
        usort(
            $birthdaysList,
            fn($a, $b) => $a['days_until'] <=> $b['days_until']
        );
    
        usort(
            $specialDaysList,
            fn($a, $b) => $a['days_until'] <=> $b['days_until']
        );
    
        /*
        |--------------------------------------------------------------------------
        | Remove Duplicate Special Days
        |--------------------------------------------------------------------------
        */
    
        $seen = [];
    
        $specialDaysList = array_values(array_filter(
            $specialDaysList,
            function ($item) use (&$seen) {
                $key = ($item['employee_id'] ?? '') . '|' .
                    ($item['date'] ?? '') . '|' .
                    ($item['type'] ?? '');
    
                if (isset($seen[$key])) {
                    return false;
                }
    
                $seen[$key] = true;
                return true;
            }
        ));
    
        /*
        |--------------------------------------------------------------------------
        | Remove Duplicate Birthdays
        |--------------------------------------------------------------------------
        */
    
        $seenB = [];
    
        $birthdaysList = array_values(array_filter(
            $birthdaysList,
            function ($item) use (&$seenB) {
                $key = ($item['employee_id'] ?? '') . '|' .
                    ($item['date'] ?? '');
    
                if (isset($seenB[$key])) {
                    return false;
                }
    
                $seenB[$key] = true;
                return true;
            }
        ));
    
        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */
    
        return $this->success([
            'stats' => [
                'today' => $todayStats,
                'yesterday' => $yesterdayStats,
            ],
    
            'charts' => [
                'punch_chart' => $punchChartData,
                'weekly_attendance' => [
                    'labels' => $weeklyLabels,
                    'data' => $weeklyData
                ]
            ],
    
            'recent_data' => [
                'organization_files' => $organization_files,
                'agreements' => $agreements,
                'others' => $others,
                'hr' => $hr,
                'employees' => $employees,
                'folders' => $folders,
                'leaves_today' => $todayLeaves,
                'birthdays' => $birthdaysList,
                'special_days' => $specialDaysList,
                'birthdays_today' => $todayBirthdays,
                'special_days_today' => $todaySpecialDays,
            ],
    
            'metadata' => [
                'share_with' => $share_with,
                'parties' => $parties,
            ],
    
            'tasks' => $tasks,
            'projects' => $projects,
    
            'leaves_today' => $todayLeaves,
            'employees_leaves_today' => $todayLeaves,
    
            'birthdays' => $birthdaysList,
            'special_days' => $specialDaysList,
    
            'birthdays_today' => $todayBirthdays,
            'special_days_today' => $todaySpecialDays,
        ]);
    }

    /**
     * Get summary statistics for the dashboard cards.
     */
    public function getSummaryStats(): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $activeEmployees = Employee::whereHas('user', function ($q) {
            $q->where('status', 'active');
        })->count();
        $inactiveEmployees = Employee::whereHas('user', function ($q) {
            $q->where('status', 'inactive');
        })->count();
        $totalEmployees = $activeEmployees + $inactiveEmployees;

        $todayLogs = AttendanceLog::whereDate('log_date', $today)
            ->select('userid', DB::raw('MIN(punch_in) as punch_in'), DB::raw('MAX(punch_out) as punch_out'))
            ->groupBy('userid')
            ->get();

        $punchedInCount = $todayLogs->filter(function ($log) {
            $punchIn = $log->punch_in ? Carbon::parse($log->punch_in)->format('H:i:s') : null;
            return $punchIn && $punchIn <= '12:00:00';
        })->count();

        $punchedOutCount = $todayLogs->filter(function ($log) {
            $punchOut = $log->punch_out ? Carbon::parse($log->punch_out)->format('H:i:s') : null;
            return $punchOut && $punchOut >= '12:00:00';
        })->count();

        $lateCount = $todayLogs->filter(function ($log) {
            $punchIn = $log->punch_in ? Carbon::parse($log->punch_in)->format('H:i:s') : null;
            return $punchIn && $punchIn >= '08:11:00' && $punchIn <= '12:00:00';
        })->count();

        $absentCount = $activeEmployees - $punchedInCount;

        $onLeaveCount = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();

        return $this->success([
            'employees' => [
                'total' => $totalEmployees,
                'active' => $activeEmployees,
                'inactive' => $inactiveEmployees,
            ],
            'attendance_today' => [
                'punched_in' => $punchedInCount,
                'punched_out' => $punchedOutCount,
                'late' => $lateCount,
                'absent' => $absentCount > 0 ? $absentCount : 0,
                'on_leave' => $onLeaveCount,
            ]
        ]);
    }

    /**
     * Get complex chart data.
     */
    public function getDetailedChartData(): JsonResponse
    {
        // Monthly Attendance (Last 6 months)
        $monthlyAttendance = AttendanceLog::select(
            DB::raw("DATE_FORMAT(log_date, '%b %Y') as month"),
            DB::raw("count(DISTINCT userid, DATE(log_date)) as count")
        )
            ->where('log_date', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy(DB::raw('MIN(log_date)'))
            ->get();

        // Late Employees Trend (Last 30 days)
        $lateTrend = DB::table('attendance_logs')
            ->select(DB::raw("DATE(log_date) as date"), DB::raw("count(*) as count"))
            ->whereRaw("TIME(punch_in) >= '08:11:00' AND TIME(punch_in) <= '12:00:00'")
            ->where('log_date', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Department-wise Distribution
        $deptDistribution = Employee::join('users', 'employees.user_id', '=', 'users.id')
            ->join('departments', 'users.department_id', '=', 'departments.id')
            ->select('departments.name as department', DB::raw('count(*) as count'))
            ->groupBy('departments.name')
            ->get();

        return $this->success([
            'monthly_attendance' => [
                'labels' => $monthlyAttendance->pluck('month'),
                'data' => $monthlyAttendance->pluck('count')
            ],
            'late_trend' => [
                'labels' => $lateTrend->pluck('date'),
                'data' => $lateTrend->pluck('count')
            ],
            'department_distribution' => [
                'labels' => $deptDistribution->pluck('department'),
                'data' => $deptDistribution->pluck('count')
            ]
        ]);
    }

    /**
     * Whether the current user is an admin (sees all notifications).
     */
    private function isAdmin(): bool
    {
        $user = auth()->user();
        return $user && strtolower($user->type ?? '') === 'admin';
    }

    /**
     * Apply special_day filter on a notifications query/collection for non-admin users.
     * Accepts an Eloquent builder or morph-many relationship.
     */
    private function applyNotificationFilter($query)
    {
        $user = auth()->user();
        $userType = strtolower($user->type ?? '');

        if ($userType === 'admin') {
            return $query;
        }

        if ($userType === 'hr' || $userType === 'admin') {
            return $query->whereIn('data->type', ['special_day', 'document', 'employee_document', 'domain_expiry', 'project_email_expiry']);
        }

        return $query->where('data->type', 'special_day');
    }

    /**
     * Get unread notifications.
     * Admins see all; non-admins see only special_day.
     */
    public function getNotifications(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('User not found', 401);
        }

        $notifications = $this->applyNotificationFilter(
            $user->unreadNotifications()->getQuery()
        )->latest()->get();

        return $this->success($notifications);
    }

    public function getReadNotifications(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return $this->error('User not found', 401);
        }

        $notifications = $this->applyNotificationFilter(
            $user->readNotifications()
        )->latest()->get();

        return $this->success($notifications);
    }

    public function getAllNotifications(): JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('User not found', 401);
        }

        $notifications = $this->applyNotificationFilter(
            $user->notifications()
        )->latest()->get();

        return $this->success($notifications);
    }

    public function showNotification($id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return $this->error('User not found', 401);
        }

        $query = $this->applyNotificationFilter($user->notifications());
        $notification = $query->find($id);

        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        return $this->success($notification);
    }

    /**
     * Mark notification as read.
     * Non-admin users can only mark their own special_day notifications as read.
     */
    public function markAsRead($id): JsonResponse
    {
        $user = auth()->user();
        $query = $this->applyNotificationFilter($user->notifications());
        $notification = $query->find($id);

        if ($notification) {
            $notification->markAsRead();
            return $this->success(null, 'Notification marked as read');
        }

        return $this->error('Notification not found', 404);
    }

    public function markAllAsRead(): JsonResponse
    {
        $user = auth()->user();
        $query = $this->applyNotificationFilter($user->notifications())
            ->whereNull('read_at');

        $query->update(['read_at' => now()]);

        return $this->success(null, 'All notifications marked as read');
    }

    /**
     * Helper to get attendance stats by date.
     */
    private function getAttendanceStatsByDate($date): array
    {
        $totalEmployees = Employee::whereHas('user', function ($query) {
            $query->where('status', 'active')
                ->where('type', '!=', 'admin');
        })->count();

        $logs = AttendanceLog::whereDate('log_date', $date)
            ->select(
                'userid',
                DB::raw('MIN(punch_in) as punch_in'),
                DB::raw('MAX(punch_out) as punch_out')
            )
            ->groupBy('userid')
            ->get();

        $presentCount = $logs->count();

        $punchedIn = $logs->filter(function ($log) {
            $punchIn = $log->punch_in ? Carbon::parse($log->punch_in)->format('H:i:s') : null;
            return $punchIn && $punchIn <= '12:00:00';
        })->count();

        $punchedOut = $logs->filter(function ($log) {
            $punchOut = $log->punch_out ? Carbon::parse($log->punch_out)->format('H:i:s') : null;
            return $punchOut && $punchOut >= '12:00:00';
        })->count();

        $lateCount = $logs->filter(function ($log) {
            $punchIn = $log->punch_in ? Carbon::parse($log->punch_in)->format('H:i:s') : null;
            return $punchIn && $punchIn >= '08:11:00' && $punchIn <= '12:00:00';
        })->count();

        $absentCount = $totalEmployees - $presentCount;

        $onLeaveCount = LeaveRequest::where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->count();

        return [
            'punched_in' => $punchedIn,
            'punched_out' => $punchedOut,
            'late' => $lateCount,
            'absent' => $absentCount > 0 ? $absentCount : 0,
            'present' => $presentCount,
            'on_leave' => $onLeaveCount
        ];
    }
}
