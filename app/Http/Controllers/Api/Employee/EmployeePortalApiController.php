<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceLog;
use App\Models\Task;
use App\Models\TaskReport;
use App\Models\WfhRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LeaveAllocation;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class EmployeePortalApiController extends ApiController
{
    /**
     * Get Employee Dashboard Data
     */
    public function dashboard(): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user)
            return $this->error('Unauthorized', 401);

        $employee = $user;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $user->load('department', 'company', 'designation', 'role');

        $today = Carbon::today()->toDateString();

        // Attendance stats for today
        $attendance = AttendanceLog::where('userid', $user->id)
            ->whereDate('log_date', $today)
            ->select('punch_in', 'punch_out', 'punch_in_latitude', 'punch_in_longitude', 'punch_in_address', 'punch_out_latitude', 'punch_out_longitude', 'punch_out_address', 'working_hours')
            ->first();

        // Store raw punch_out status BEFORE formatting
        $isPunchedOut = $attendance && !is_null($attendance->punch_out);

        if ($attendance) {
            $tz = config('app.timezone', 'Asia/Dubai');

            $attendance->punch_in = $attendance->punch_in
                ? Carbon::parse($attendance->punch_in)->setTimezone($tz)->format('h:i A')
                : '--';

            $attendance->punch_out = $attendance->punch_out
                ? Carbon::parse($attendance->punch_out)->setTimezone($tz)->format('h:i A')
                : '--';

            // Format working_minutes → "8 hrs 30 mins"
            $minutes = $attendance->working_hours ?? 0;
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;

            if ($minutes == 0) {
                $attendance->working_hours = '--';
            } elseif ($hours == 0) {
                $attendance->working_hours = "{$mins} mins";
            } elseif ($mins == 0) {
                $attendance->working_hours = "{$hours} hrs";
            } else {
                $attendance->working_hours = "{$hours} hrs {$mins} mins";
            }
        }

        // 30-day attendance history
        $from = Carbon::now()->subDays(30)->startOfDay();
        $to = Carbon::now()->endOfDay();
        $days_present = AttendanceLog::where('userid', $user->id)
            ->whereMonth('log_date', date('m'))
            ->count();

        $attendanceHistory = AttendanceLog::where('userid', $user->id)
            ->whereBetween('log_date', [$from, $to])
            ->select('log_date', 'punch_in', 'punch_out', 'punch_in_latitude', 'punch_in_longitude', 'punch_in_address', 'punch_out_latitude', 'punch_out_longitude', 'punch_out_address', 'working_hours')
            ->orderByDesc('log_date')
            ->get();

        if ($attendanceHistory) {
            $attendanceHistory->transform(function ($log) {
                $tz = config('app.timezone', 'Asia/Kolkata');

                $log->log_date = $log->log_date
                    ? Carbon::parse($log->log_date)->format('d/m/Y')
                    : '--';

                $log->punch_in = $log->punch_in
                    ? Carbon::parse($log->punch_in)->setTimezone($tz)->format('h:i A')
                    : '--';

                $log->punch_out = $log->punch_out
                    ? Carbon::parse($log->punch_out)->setTimezone($tz)->format('h:i A')
                    : '--';

                // Format working_hours → "8 hrs 30 mins"
                $minutes = $log->working_hours ?? 0;
                $hours = intdiv($minutes, 60);
                $mins = $minutes % 60;

                if ($minutes == 0)
                    $log->working_hours = '--';
                elseif ($hours == 0)
                    $log->working_hours = "{$mins} mins";
                elseif ($mins == 0)
                    $log->working_hours = "{$hours} hrs";
                else
                    $log->working_hours = "{$hours} hrs {$mins} mins";

                return $log;
            });
        }

        // Leave stats
        $totalLeavesTaken = LeaveRequest::where('employee_id', $user->employee->id)
            ->where('status', 'approved')
            ->whereYear('start_date', date('Y'))
            ->sum('duration_days');

        $totalAllocatedDays = LeaveAllocation::where('employee_id', $employee->id)
            ->whereYear('year', date('Y'))
            ->sum('allocated_days');

        $leaveBalance = $totalAllocatedDays - $totalLeavesTaken;

        // Punch Access Logic
        $canPunch = true;

        // 1. Check default designation punch access
        if ($employee->designation && $employee->designation->default_punch_access) {
            $canPunch = true;
        }

        // 2. Specific designation checks
        if (!$canPunch && ($employee->designation && in_array($employee->designation->name, ['Delivery Man', 'Salesperson']))) {
            $canPunch = true;
        }

        // 3. Check for approved WFH request today
        if (!$canPunch) {
            $canPunch = WfhRequest::where('employee_id', $user->employee->id)
                ->whereDate('date', $today)
                ->where('status', 'Approved')
                ->exists();
        }

        // ── Task sections ──────────────────────────────────────────────
        $employeeId = $user->employee->id;

        // Base query: all tasks assigned to this employee (with pivot status)
        $baseTaskQuery = Task::with([
            'project:id,project_name',
            'assignedTo' => function ($q) use ($employeeId) {
                $q->where('employees.id', $employeeId)
                    ->select('employees.id')
                    ->withPivot('status');
            }
        ])
            ->whereHas('assignedTo', function ($q) use ($employeeId) {
                $q->where('employees.id', $employeeId);
            });

        // Today's assigned tasks (assigned_date = today)
        $todayAssignedTasks = (clone $baseTaskQuery)
            ->whereDate('assigned_date', $today)
            ->get()
            ->map(fn($task) => $this->formatTaskForEmployee($task, $employeeId));

        // All tasks
        $allTasks = (clone $baseTaskQuery)
            ->latest()
            ->get()
            ->map(fn($task) => $this->formatTaskForEmployee($task, $employeeId));

        // Tasks in progress
        $tasksInProgress = (clone $baseTaskQuery)
            ->whereHas('assignedTo', function ($q) use ($employeeId) {
                $q->where('employees.id', $employeeId)
                    ->where('task_employee.status', 'in_progress');
            })
            ->get()
            ->map(fn($task) => $this->formatTaskForEmployee($task, $employeeId));

        // Tasks completed
        $tasksCompleted = (clone $baseTaskQuery)
            ->whereHas('assignedTo', function ($q) use ($employeeId) {
                $q->where('employees.id', $employeeId)
                    ->where('task_employee.status', 'completed');
            })
            ->get()
            ->map(fn($task) => $this->formatTaskForEmployee($task, $employeeId));

        // Tasks on hold
        $tasksOnHold = (clone $baseTaskQuery)
            ->whereHas('assignedTo', function ($q) use ($employeeId) {
                $q->where('employees.id', $employeeId)
                    ->where('task_employee.status', 'on_hold');
            })
            ->get()
            ->map(fn($task) => $this->formatTaskForEmployee($task, $employeeId));

        // ── Leaves Today (for Team Lead & HR only) ─────────────────────
        $userType = strtolower($user->type ?? '');
        $roleName = strtolower($user->role?->name ?? '');
        $designationName = strtolower($user->designation?->name ?? '');

        $isHr = in_array($userType, ['hr', 'hr manager', 'hr_manager', 'admin'])
            || str_contains($roleName, 'hr')
            || str_contains($designationName, 'hr');

        $isTeamLead = in_array($userType, ['team_lead', 'teamlead', 'team lead', 'tl'])
            || str_contains($roleName, 'team lead')
            || str_contains($roleName, 'lead')
            || str_contains($designationName, 'team lead')
            || str_contains($designationName, 'lead');

        $leavesToday = [];
        $leavesTodayByDepartment = [];

        if ($isHr || $isTeamLead) {
            $leavesTodayQuery = LeaveRequest::with([
                'employee.user.department',
                'employee.user.designation',
                'leaveType'
            ])
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today);

            // Team Lead: filter to their own department only
            if (!$isHr && $isTeamLead) {
                $deptId = $user->department_id;
                if ($deptId) {
                    $leavesTodayQuery->whereHas('employee.user', function ($q) use ($deptId) {
                        $q->where('department_id', $deptId);
                    });
                }
            }

            $mappedLeaves = $leavesTodayQuery->get()->map(function ($leave) {
                $emp = $leave->employee;
                $usr = $emp?->user;
                $employeeName = $emp ? trim(($emp->first_name ?? '') . ' ' . ($emp->last_name ?? '')) : null;
                if (empty($employeeName) && $usr) {
                    $employeeName = $usr->username;
                }
                $deptName = $usr?->department?->name ?? 'Unassigned';

                return [
                    'id' => $leave->id,
                    'employee_id' => $leave->employee_id,
                    'employee_name' => $employeeName,
                    'department_id' => $usr?->department_id,
                    'department_name' => $deptName,
                    'designation' => $usr?->designation?->name ?? null,
                    'leave_type' => $leave->leaveType?->name ?? null,
                    'start_date' => $leave->start_date ? (is_string($leave->start_date) ? $leave->start_date : $leave->start_date->format('Y-m-d')) : null,
                    'end_date' => $leave->end_date ? (is_string($leave->end_date) ? $leave->end_date : $leave->end_date->format('Y-m-d')) : null,
                    'duration_days' => $leave->duration_days,
                    'reason' => $leave->reason,
                    'status' => $leave->status,
                    'session1' => $leave->session1,
                    'session2' => $leave->session2,
                ];
            });

            $leavesToday = $mappedLeaves->values()->toArray();
            $leavesTodayByDepartment = $mappedLeaves->groupBy('department_name')->toArray();
        }

        $todayCarbon = Carbon::today();
        $todayMonth = $todayCarbon->month;
        $todayDay = $todayCarbon->day;

        $allEmployees = Employee::with([
            'user.department',
            'user.designation',
        ])
            ->whereHas('user', function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        $birthdaysList = [];
        $specialDaysList = [];
        $todayBirthdays = [];
        $todaySpecialDays = [];

        foreach ($allEmployees as $empItem) {
            $empUser = $empItem->user;
            $empName = trim(($empItem->first_name ?? '') . ' ' . ($empItem->last_name ?? ''));
            if (empty($empName) && $empUser) {
                $empName = $empUser->username;
            }

            $deptName = $empUser?->department?->name ?? null;
            $designationName = $empUser?->designation?->name ?? null;

            // 1. Birthday
            if (!empty($empItem->dob)) {
                try {
                    $dob = Carbon::parse($empItem->dob);
                    $bdayThisYear = Carbon::create($todayCarbon->year, $dob->month, $dob->day)->startOfDay();
                    if ($bdayThisYear->lt($todayCarbon)) {
                        $nextBday = Carbon::create($todayCarbon->year + 1, $dob->month, $dob->day)->startOfDay();
                    } else {
                        $nextBday = $bdayThisYear;
                    }
                    $isToday = ($dob->month === $todayMonth && $dob->day === $todayDay);
                    $daysUntil = (int) $todayCarbon->diffInDays($nextBday, false);

                    $bdayItem = [
                        'id' => $empItem->id,
                        'employee_id' => $empItem->employee_id,
                        'employee_name' => $empName,
                        'department_name' => $deptName,
                        'designation' => $designationName,
                        'dob' => $dob->format('Y-m-d'),
                        'date' => $bdayThisYear->format('Y-m-d'),
                        'next_date' => $nextBday->format('Y-m-d'),
                        'is_today' => $isToday,
                        'days_until' => $daysUntil,
                        'type' => 'Birthday',
                        'event_name' => "{$empName}'s Birthday",
                    ];

                    $birthdaysList[] = $bdayItem;
                    if ($isToday) {
                        $todayBirthdays[] = $bdayItem;
                    }

                    $specialDaysList[] = $bdayItem;
                    if ($isToday) {
                        $todaySpecialDays[] = $bdayItem;
                    }
                } catch (\Exception $e) {
                    // Ignore parse error
                }
            }

            // 2. Work Anniversary
            if (!empty($empItem->joining_date)) {
                try {
                    $joiningDate = Carbon::parse($empItem->joining_date);
                    if ($joiningDate->year < $todayCarbon->year) {
                        $annivThisYear = Carbon::create($todayCarbon->year, $joiningDate->month, $joiningDate->day)->startOfDay();
                        if ($annivThisYear->lt($todayCarbon)) {
                            $nextAnniv = Carbon::create($todayCarbon->year + 1, $joiningDate->month, $joiningDate->day)->startOfDay();
                        } else {
                            $nextAnniv = $annivThisYear;
                        }
                        $isToday = ($joiningDate->month === $todayMonth && $joiningDate->day === $todayDay);
                        $years = $todayCarbon->year - $joiningDate->year;
                        $daysUntil = (int) $todayCarbon->diffInDays($nextAnniv, false);

                        $eventName = "{$years} Year Work Anniversary";

                        $annivItem = [
                            'id' => $empItem->id,
                            'employee_id' => $empItem->employee_id,
                            'employee_name' => $empName,
                            'department_name' => $deptName,
                            'designation' => $designationName,
                            'type' => 'Work Anniversary',
                            'event_name' => "{$empName}'s {$eventName}",
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
                } catch (\Exception $e) {
                    // Ignore parse error
                }
            }

            // 3. Custom Special Days
            if (!empty($empItem->special_days) && is_array($empItem->special_days)) {
                foreach ($empItem->special_days as $sDay) {
                    $sDateStr = is_array($sDay) ? ($sDay['date'] ?? null) : null;
                    $sName = is_array($sDay) ? ($sDay['name'] ?? 'Special Day') : 'Special Day';

                    if ($sDateStr) {
                        try {
                            $sDate = Carbon::parse($sDateStr);
                            $sThisYear = Carbon::create($todayCarbon->year, $sDate->month, $sDate->day)->startOfDay();
                            if ($sThisYear->lt($todayCarbon)) {
                                $nextSDay = Carbon::create($todayCarbon->year + 1, $sDate->month, $sDate->day)->startOfDay();
                            } else {
                                $nextSDay = $sThisYear;
                            }
                            $isToday = ($sDate->month === $todayMonth && $sDate->day === $todayDay);
                            $daysUntil = (int) $todayCarbon->diffInDays($nextSDay, false);

                            $customItem = [
                                'id' => $empItem->id,
                                'employee_id' => $empItem->employee_id,
                                'employee_name' => $empName,
                                'department_name' => $deptName,
                                'designation' => $designationName,
                                'type' => 'Custom',
                                'event_name' => "{$empName}'s {$sName}",
                                'date' => $sThisYear->format('Y-m-d'),
                                'is_today' => $isToday,
                                'days_until' => $daysUntil,
                            ];

                            $specialDaysList[] = $customItem;
                            if ($isToday) {
                                $todaySpecialDays[] = $customItem;
                            }
                        } catch (\Exception $e) {
                            // Ignore parse error
                        }
                    }
                }
            }
        }

        // Sort lists by days_until (upcoming first)
        usort($birthdaysList, fn($a, $b) => $a['days_until'] <=> $b['days_until']);
        usort($specialDaysList, fn($a, $b) => $a['days_until'] <=> $b['days_until']);

        return $this->success([
            'employee' => $user->employee,
            'days_present' => $days_present,
            'today_attendance' => [
                'punched_in' => (bool) $attendance,
                'punched_out' => $isPunchedOut,
                'punch_in_time' => $attendance ? $attendance->punch_in : null,
                'punch_out_time' => $attendance ? $attendance->punch_out : null,
                'punch_in_location' => [
                    'latitude' => $attendance ? $attendance->punch_in_latitude : null,
                    'longitude' => $attendance ? $attendance->punch_in_longitude : null,
                    'address' => $attendance ? $attendance->punch_in_address : null
                ],
                'punch_out_location' => [
                    'latitude' => $attendance ? $attendance->punch_out_latitude : null,
                    'longitude' => $attendance ? $attendance->punch_out_longitude : null,
                    'address' => $attendance ? $attendance->punch_out_address : null
                ],
                'working_hours' => $attendance ? $attendance->working_hours : '--'
            ],
            'leave_stats' => [
                'total_taken' => (float) max(0, $totalLeavesTaken),
                'balance' => (float) max(0, $leaveBalance),
                'allocated' => (float) max(0, $totalAllocatedDays),
            ],
            'attendance_history' => $attendanceHistory,
            'can_punch' => $canPunch,
            'pending_wfh_count' => WfhRequest::where('employee_id', $user->employee->id)->where('status', 'pending')->count(),
            'recent_leaves' => LeaveRequest::where('employee_id', $user->employee->id)->latest()->take(5)->get(),
            'leaves_today' => $leavesToday,
            'employees_leaves_today' => $leavesToday,
            'leaves_today_by_department' => $leavesTodayByDepartment,
            'is_hr' => $isHr,
            'is_team_lead' => $isTeamLead,
            'tasks' => [
                'today_assigned_tasks' => $todayAssignedTasks,
                'all_tasks' => $allTasks,
                'in_progress' => $tasksInProgress,
                'completed' => $tasksCompleted,
                'on_hold' => $tasksOnHold,
            ],
            'birthdays' => $birthdaysList,
            'special_days' => $specialDaysList,
            'birthdays_today' => $todayBirthdays,
            'special_days_today' => $todaySpecialDays,
            'employee_special_days' => $specialDaysList,
            'employee_birthdays' => $birthdaysList,
        ]);
    }

    /**
     * Format a Task for the employee's dashboard view.
     * Extracts the current employee's pivot status from the assignedTo collection.
     */
    private function formatTaskForEmployee(Task $task, int $employeeId): array
    {
        // The pivot for this employee (loaded via assignedTo relationship)
        $pivot = $task->assignedTo->firstWhere('id', $employeeId)?->pivot;

        return [
            'id' => $task->id,
            'title' => $task->title,
            'task_description' => $task->task_description,
            'project' => $task->project ? [
                'id' => $task->project->id,
                'project_name' => $task->project->project_name,
            ] : null,
            'assigned_by' => $task->assigned_by,
            'assigned_date' => $task->assigned_date,
            'due_date' => $task->due_date,
            'priority' => $task->priority,
            'status' => $pivot?->status ?? 'assigned',
        ];
    }



    /**
     * Punch In
     */
    public function punchIn(Request $request): JsonResponse
    {
        $request->validate([
            'punch_in_latitude' => 'nullable|numeric',
            'punch_in_longitude' => 'nullable|numeric',
            'punch_in_address' => 'nullable|string',
        ]);
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        // Check for missing punch out on previous records
        $missingPunchOut = AttendanceLog::where('userid', $user->id)
            ->whereNull('punch_out')
            ->orderBy('log_date', 'desc')
            ->first();

        if ($missingPunchOut) {
            return $this->error("You have a pending punch-out for {$missingPunchOut->log_date}. Please complete your project timings and punch out for that day first.", 403);
        }

        $today = Carbon::today()->toDateString();

        $alreadyPunched = AttendanceLog::where('userid', $user->id)
            ->whereDate('log_date', $today)
            ->exists();

        if ($alreadyPunched) {
            return $this->error('Already punched in today.', 400);
        }

        $log = AttendanceLog::create([
            // 'company_id' => $user->company_id ?? 1,
            'userid' => $user->id,
            'log_date' => $today,
            'punch_in' => Carbon::now(),
            'status' => 1,
            'log_status' => 'IN',
            'punch_in_latitude' => $request->punch_in_latitude,
            'punch_in_longitude' => $request->punch_in_longitude,
            'punch_in_address' => $request->punch_in_address,

        ]);

        return $this->success($log, 'Punched in successfully.', 201);
    }

    /**
     * Punch Out
     */
    public function punchOut(Request $request): JsonResponse
    {
        $request->validate([
            'punch_out_latitude' => 'nullable|numeric',
            'punch_out_longitude' => 'nullable|numeric',
            'punch_out_address' => 'nullable|string',
            'tasks_completed' => 'required|string',
            'pending_tasks' => 'nullable|string',
            'plan_tomorrow' => 'nullable|string',
            'remarks' => 'nullable|string',
            'punch_out_time' => 'nullable|date',
            'is_overtime' => 'nullable|boolean'
        ]);

        $user = auth('api')->user();

        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $employee = $user->employee;

        if (!$employee) {
            return $this->error('Employee profile not found', 404);
        }

        // Find the oldest attendance record that has not been punched out
        $log = AttendanceLog::where('userid', $user->id)
            ->whereNull('punch_out')
            ->orderBy('log_date', 'asc')
            ->first();

        if (!$log) {
            return $this->error('No active punch-in found.', 400);
        }

        $attendanceDate = Carbon::parse($log->log_date)->toDateString();
        $punchOutTime = Carbon::parse($request->punch_out_time);

        //calculation of working hours
        $punchIn = Carbon::parse($log->punch_in);
        $totalMinutes = $punchIn->diffInMinutes($punchOutTime);
        $breakMinutes = (int) $log->breaks()->sum('duration_minutes');
        $excessBreakMinutes = max(0, $breakMinutes - 60);
        $workingMinutes = max(0, $totalMinutes - $excessBreakMinutes);

        // Validate punch out date matches attendance date
        if ($punchOutTime->toDateString() !== $attendanceDate) {
            return $this->error(
                "Punch out time must belong to the attendance date {$attendanceDate}.",
                422
            );
        }

        // Ensure punch out is after punch in
        if ($log->punch_in && $punchOutTime->lt(Carbon::parse($log->punch_in))) {
            return $this->error(
                'Punch out time cannot be earlier than punch in time.',
                422
            );
        }

        // Save task report
        TaskReport::updateOrCreate(
            [
                'employee_id' => $user->id,
                'date' => $attendanceDate,
            ],
            [
                'tasks_completed' => $request->tasks_completed,
                'pending_tasks' => $request->pending_tasks,
                'plan_tomorrow' => $request->plan_tomorrow,
                'remarks' => $request->remarks,
            ]
        );

        // Update attendance log
        $log->update([
            'punch_out' => $punchOutTime,
            'working_hours' => $workingMinutes,
            'punch_out_latitude' => $request->latitude,
            'punch_out_longitude' => $request->longitude,
            'punch_out_address' => $request->address,
            'log_status' => 'OUT',
            'is_overtime' => $request->is_overtime ? 1 : 0
        ]);

        $message = $attendanceDate === now()->toDateString()
            ? 'Punched out successfully and task report submitted.'
            : "Previous attendance dated {$attendanceDate} has been punched out successfully and task report submitted.";

        return $this->success([
            'attendance_date' => $attendanceDate,
            'punch_in' => $log->punch_in,
            'punch_out' => $punchOutTime,
            'attendance_log' => $log->fresh(),
        ], $message);
    }

    /**
     * Start Break
     */
    public function startBreak(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user || !$user->employee) {
            return $this->error('Employee profile not found', 404);
        }

        $log = AttendanceLog::where('userid', $user->id)
            ->whereNull('punch_out')
            ->orderBy('log_date', 'desc')
            ->first();

        if (!$log) {
            return $this->error('You must punch in before starting a break.', 403);
        }

        $activeBreak = $log->breaks()->whereNull('end_time')->first();
        if ($activeBreak) {
            return $this->error('You are already on a break.', 400);
        }

        $timezone = $request->input('timezone', config('app.timezone'));

        $break = $log->breaks()->create([
            'start_time' => Carbon::now($timezone),
        ]);

        return $this->success($break, 'Break started successfully.', 201);
    }

    /**
     * End Break
     */
    public function endBreak(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if (!$user || !$user->employee) {
            return $this->error('Employee profile not found', 404);
        }

        $log = AttendanceLog::where('userid', $user->id)
            ->whereNull('punch_out')
            ->orderBy('log_date', 'desc')
            ->first();

        if (!$log) {
            return $this->error('No active punch in found.', 400);
        }

        $activeBreak = $log->breaks()->whereNull('end_time')->first();
        if (!$activeBreak) {
            return $this->error('You are not currently on a break.', 400);
        }

        $timezone = $request->input('timezone', config('app.timezone'));
        $now = Carbon::now($timezone);
        $durationInSeconds = $activeBreak->start_time->diffInSeconds($now);
        $duration = (int) ceil($durationInSeconds / 60);

        $activeBreak->update([
            'end_time' => $now,
            'duration_minutes' => $duration
        ]);

        return $this->success($activeBreak, 'Break ended successfully.');
    }

    /**
     * Get Breaks by date
     */
    public function getBreaks(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user || !$user->employee) {
            return $this->error('Employee profile not found', 404);
        }

        $attendanceLogs = AttendanceLog::with('breaks')
            ->where('userid', $user->id)
            ->orderBy('log_date', 'desc')
            ->get();

        if ($attendanceLogs->isEmpty()) {
            return $this->success([
                'breaks' => [],
                'total_break_minutes' => 0,
            ], 'No attendance logs found.');
        }

        $breaks = $attendanceLogs->flatMap(function ($log) {
            return $log->breaks->map(function ($break) use ($log) {
                return [
                    'id' => $break->id,
                    'attendance_log_id' => $log->id,
                    'date' => $log->log_date,
                    'start_time' => $break->start_time,
                    'end_time' => $break->end_time,
                    'duration_minutes' => $break->duration_minutes,
                    'created_at' => $break->created_at,
                    'updated_at' => $break->updated_at,
                ];
            });
        })->values();

        return $this->success([
            'breaks' => $breaks,
            'total_break_minutes' => $breaks->sum('duration_minutes'),
        ]);
    }


    /**
     * Leaves
     */
    public function leaves(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leaves = LeaveRequest::with('leaveType')->where('employee_id', $employee->id)->latest()->get();
        return $this->success([
            'leaves' => $leaves
        ]);
    }

    public function showLeave($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leave = LeaveRequest::with(['leaveType'])->where('employee_id', $employee->id)->find($id);

        if (!$leave)
            return $this->error('Leave request not found', 404);

        return $this->success($leave);
    }

    public function leaveTypesAndBalance(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leaveTypes = LeaveType::where('status', true)->get();
        $currentYear = date('Y');

        $totalAllocated = 0;
        $totalTaken = 0;
        $totalBalance = 0;

        $leaveTypesData = $leaveTypes->map(function ($leaveType) use ($employee, $currentYear, &$totalAllocated, &$totalTaken, &$totalBalance) {
            $allocation = LeaveAllocation::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $currentYear)
                ->first();

            $taken = (float) LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->whereYear('start_date', $currentYear)
                ->sum('duration_days');

            $pending = (float) LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'pending')
                ->whereYear('start_date', $currentYear)
                ->sum('duration_days');

            $allocated = $allocation ? (float) $allocation->allocated_days : 0;
            $balance = $allocated - $taken;

            $totalAllocated += $allocated;
            $totalTaken += $taken;
            $totalBalance += $balance;

            return [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'status' => $leaveType->status,
                'allocated' => $allocated,
                'taken' => $taken,
                'pending' => $pending,
                'balance' => $balance,
            ];
        });

        return $this->success([
            'leave_types' => $leaveTypesData,
            'total_allocated' => $totalAllocated,
            'leaves_taken' => $totalTaken,
            'remaining_balance' => $totalBalance,
        ]);
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10',
            'claim_salary' => 'nullable|boolean',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'session1' => 'nullable|in:morning,afternoon',
            'session2' => 'nullable|in:morning,afternoon',
            'employee_id' => 'nullable',
        ]);

        if ($request->filled('employee_id')) {
            $employee = Employee::find($request->employee_id);
        } else {
            $user = auth('api')->user();
            $employee = $user ? $user->employee : null;
        }

        if (!$employee) {
            return $this->error('Employee profile not found', 404);
        }

        // Check if there are overlapping leaves considering session1 and session2
        $hasOverlap = $this->hasOverlappingLeave(
            $employee->id,
            $request->start_date,
            $request->end_date,
            $request->session1,
            $request->session2
        );

        if ($hasOverlap) {
            return $this->error('You have already applied/taken leave on the selected date(s) and session(s).', 422);
        }

        $leaveType = LeaveType::find($request->leave_type_id);

        // Check for sick leave document
        if ($leaveType && str_contains(strtolower($leaveType->name), 'sick') && !$request->hasFile('document')) {
            return $this->error('Medical certificate is required for sick leave', 422);
        }

        // Find or prepare LOP Leave Type
        $lopLeaveType = LeaveType::where(function ($q) {
            $q->where('name', 'LIKE', '%LOP%')
                ->orWhere('name', 'LIKE', '%Loss of Pay%')
                ->orWhere('name', 'LIKE', '%Unpaid%');
        })->first();

        if (!$lopLeaveType) {
            $lopLeaveType = LeaveType::create([
                'name' => 'LOP',
                'status' => true,
            ]);
        }

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $session1 = $request->session1;
        $session2 = $request->session2;

        $durationDays = 0.0;
        $currentDate = $start->copy();

        if ($start->isSameDay($end)) {
            if ($currentDate->isSunday()) {
                $durationDays = 0.0;
            } elseif (empty($session1) && empty($session2)) {
                $durationDays = 1.0;
            } elseif ($session1 === 'morning' && $session2 === 'afternoon') {
                $durationDays = 1.0;
            } else {
                $durationDays = 0.5;
            }
        } else {
            while ($currentDate->lte($end)) {
                if ($currentDate->isSunday()) {
                    $currentDate->addDay();
                    continue;
                }

                if ($currentDate->isSameDay($start)) {
                    if (empty($session1) || $session1 === 'morning') {
                        $durationDays += 1.0;
                    } else {
                        $durationDays += 0.5;
                    }
                } elseif ($currentDate->isSameDay($end)) {
                    if (empty($session2) || $session2 === 'afternoon') {
                        $durationDays += 1.0;
                    } else {
                        $durationDays += 0.5;
                    }
                } else {
                    $durationDays += 1.0;
                }

                $currentDate->addDay();
            }
        }

        if ($durationDays <= 0) {
            return $this->error('Selected leave duration invalid or falls entirely on non-working days.', 422);
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leaves/documents', 'public');
        }

        $isLop = ($leaveType && ($leaveType->id == $lopLeaveType->id || preg_match('/(lop|loss of pay|unpaid)/i', $leaveType->name)));

        if ($isLop) {
            $leave = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'session1' => $session1,
                'session2' => $session2,
                'duration_days' => $durationDays,
                'claim_salary' => false,
                'document' => $documentPath,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            return $this->success($leave, 'LOP leave request submitted successfully', 201);
        }

        // Non-LOP category leave balance check
        $currentYear = date('Y');
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        $allocated = $allocation ? (float) $allocation->allocated_days : 0;

        $leavesTaken = (float) LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->sum('duration_days');

        $remainingBalance = max(0, $allocated - $leavesTaken);

        $probationStart = $employee->probation_start_date ? Carbon::parse($employee->probation_start_date) : null;
        $probationEnd = $employee->probation_end_date ? Carbon::parse($employee->probation_end_date) : null;

        if ($probationStart && $probationEnd) {
            $leaveStart = Carbon::parse($request->start_date);
            $probationRestrictionEnd = $probationEnd->copy()->endOfMonth();

            if ($leaveStart->lte($probationRestrictionEnd)) {
                $monthsSinceStart = (int) $probationStart->copy()->startOfDay()->diffInMonths($leaveStart->copy()->startOfDay());
                $probationMaxLeaves = 0;

                if ($monthsSinceStart == 0) {
                    $probationMaxLeaves = 0;
                } elseif ($monthsSinceStart == 1) {
                    $probationMaxLeaves = 1;
                } else {
                    $probationMaxLeaves = 1.5; // Up to end of probation restriction period
                }

                $leavesTakenInRestriction = (float) LeaveRequest::where('employee_id', $employee->id)
                    ->where('leave_type_id', $request->leave_type_id)
                    ->whereIn('status', ['approved', 'pending'])
                    ->whereDate('start_date', '<=', $probationRestrictionEnd)
                    ->whereDate('start_date', '>=', $probationStart)
                    ->sum('duration_days');

                $remainingProbationBalance = max(0, $probationMaxLeaves - $leavesTakenInRestriction);

                // Override the bucket balance during probation, because exact joining date
                // anniversaries (e.g. 15th) happen before the end-of-month cron runs.
                $remainingBalance = $remainingProbationBalance;
            }
        }

        if ($remainingBalance == 0) {
            return $this->error("You have 0 days remaining for {$leaveType->name}. Please apply as LOP.", 422);
        }

        // if ($durationDays > $remainingBalance) {
        //     $bucketDays = $remainingBalance;
        //     $lopDays = $durationDays - $remainingBalance;

        //     $bucketLeave = LeaveRequest::create([
        //         'employee_id' => $employee->id,
        //         'leave_type_id' => $request->leave_type_id,
        //         'start_date' => $request->start_date,
        //         'end_date' => $request->end_date,
        //         'session1' => $session1,
        //         'session2' => $session2,
        //         'duration_days' => $bucketDays,
        //         'claim_salary' => $request->claim_salary ?? false,
        //         'document' => $documentPath,
        //         'reason' => $request->reason,
        //         'status' => 'pending',
        //     ]);

        //     $lopLeave = LeaveRequest::create([
        //         'employee_id' => $employee->id,
        //         'leave_type_id' => $lopLeaveType->id,
        //         'start_date' => $request->start_date,
        //         'end_date' => $request->end_date,
        //         'session1' => $session1,
        //         'session2' => $session2,
        //         'duration_days' => $lopDays,
        //         'claim_salary' => false,
        //         'document' => $documentPath,
        //         'reason' => $request->reason . ' (Auto-split LOP)',
        //         'status' => 'pending',
        //     ]);

        //     return $this->success([
        //         'leave' => $bucketLeave,
        //         'lop_leave' => $lopLeave,
        //     ], "Leave request submitted successfully. {$bucketDays} day(s) deducted from {$leaveType->name} and {$lopDays} day(s) created under LOP.", 201);
        // }

        if ($durationDays > $remainingBalance) {
            $split = $this->splitLeaveIntoSlots($start, $end, $session1, $session2, $remainingBalance);
            $bucketDetails = $split['bucket'];
            $lopDetails = $split['lop'];

            $bucketLeave = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $bucketDetails['start_date'],
                'end_date' => $bucketDetails['end_date'],
                'session1' => $bucketDetails['session1'],
                'session2' => $bucketDetails['session2'],
                'duration_days' => $bucketDetails['duration_days'],
                'claim_salary' => $request->claim_salary ?? false,
                'document' => $documentPath,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            $lopLeave = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $lopLeaveType->id,
                'start_date' => $lopDetails['start_date'],
                'end_date' => $lopDetails['end_date'],
                'session1' => $lopDetails['session1'],
                'session2' => $lopDetails['session2'],
                'duration_days' => $lopDetails['duration_days'],
                'claim_salary' => false,
                'document' => $documentPath,
                'reason' => $request->reason . ' (Auto-split LOP)',
                'status' => 'pending',
            ]);

            return $this->success(
                [
                    'leave' => $bucketLeave,
                    'lop_leave' => $lopLeave,
                ],
                "Leave request submitted successfully. {$bucketDetails['duration_days']} day(s) deducted from {$leaveType->name} and {$lopDetails['duration_days']} day(s) created under LOP.",
                201
            );
        }

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'session1' => $session1,
            'session2' => $session2,
            'duration_days' => $durationDays,
            'claim_salary' => $request->claim_salary ?? false,
            'document' => $documentPath,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return $this->success($leave, 'Leave request submitted successfully', 201);
    }

    public function updateLeave(Request $request, $id): JsonResponse
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10',
            'claim_salary' => 'nullable|boolean',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'session1' => 'nullable|in:morning,afternoon',
            'session2' => 'nullable|in:morning,afternoon',
            'employee_id' => 'nullable',
        ]);

        if ($request->filled('employee_id')) {
            $employee = Employee::find($request->employee_id);
        } else {
            $user = auth('api')->user();
            $employee = $user ? $user->employee : null;
        }

        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leave = LeaveRequest::where('employee_id', $employee->id)->find($id);

        if (!$leave) {
            return $this->error('Leave request not found', 404);
        }

        $leaveType = LeaveType::find($request->leave_type_id);

        if ($leaveType && str_contains(strtolower($leaveType->name), 'sick') && !$request->hasFile('document') && !$leave->document) {
            return $this->error('Medical certificate is required for sick leave', 422);
        }

        // Find or prepare LOP Leave Type
        $lopLeaveType = LeaveType::where(function ($q) {
            $q->where('name', 'LIKE', '%LOP%')
                ->orWhere('name', 'LIKE', '%Loss of Pay%')
                ->orWhere('name', 'LIKE', '%Unpaid%');
        })->first();

        if (!$lopLeaveType) {
            $lopLeaveType = LeaveType::create([
                'name' => 'LOP',
                'status' => true,
            ]);
        }

        // Check if there are overlapping leaves considering session1 and session2 (excluding this leave request)
        $hasOverlap = $this->hasOverlappingLeave(
            $employee->id,
            $request->start_date,
            $request->end_date,
            $request->session1,
            $request->session2,
            $leave->id
        );

        if ($hasOverlap) {
            return $this->error('You have already applied/taken leave on the selected date(s) and session(s).', 422);
        }

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $session1 = $request->session1;
        $session2 = $request->session2;

        $durationDays = 0.0;
        $currentDate = $start->copy();

        if ($start->isSameDay($end)) {
            if ($currentDate->isSunday()) {
                $durationDays = 0.0;
            } elseif (empty($session1) && empty($session2)) {
                $durationDays = 1.0;
            } elseif ($session1 === 'morning' && $session2 === 'afternoon') {
                $durationDays = 1.0;
            } else {
                $durationDays = 0.5;
            }
        } else {
            while ($currentDate->lte($end)) {
                if ($currentDate->isSunday()) {
                    $currentDate->addDay();
                    continue;
                }

                if ($currentDate->isSameDay($start)) {
                    if (empty($session1) || $session1 === 'morning') {
                        $durationDays += 1.0;
                    } else {
                        $durationDays += 0.5;
                    }
                } elseif ($currentDate->isSameDay($end)) {
                    if (empty($session2) || $session2 === 'afternoon') {
                        $durationDays += 1.0;
                    } else {
                        $durationDays += 0.5;
                    }
                } else {
                    $durationDays += 1.0;
                }

                $currentDate->addDay();
            }
        }

        if ($durationDays <= 0) {
            return $this->error('Selected leave duration invalid or falls entirely on non-working days.', 422);
        }

        $documentPath = $leave->document;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leaves/documents', 'public');
        }

        $isLop = ($leaveType && ($leaveType->id == $lopLeaveType->id || preg_match('/(lop|loss of pay|unpaid)/i', $leaveType->name)));

        if ($isLop) {
            $leave->update([
                'leave_type_id' => $leaveType->id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'session1' => $session1,
                'session2' => $session2,
                'duration_days' => $durationDays,
                'claim_salary' => false,
                'document' => $documentPath,
                'reason' => $request->reason,
            ]);

            return $this->success($leave, 'LOP leave request updated successfully');
        }

        // Non-LOP category leave balance check
        $currentYear = date('Y');
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        $allocated = $allocation ? (float) $allocation->allocated_days : 0;

        $leavesTaken = (float) LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'approved')
            ->where('id', '!=', $leave->id)
            ->whereYear('start_date', $currentYear)
            ->sum('duration_days');

        $remainingBalance = max(0, $allocated - $leavesTaken);

        $probationStart = $employee->probation_start_date ? Carbon::parse($employee->probation_start_date) : null;
        $probationEnd = $employee->probation_end_date ? Carbon::parse($employee->probation_end_date) : null;

        if ($probationStart && $probationEnd) {
            $leaveStart = Carbon::parse($request->start_date);
            $probationRestrictionEnd = $probationEnd->copy()->endOfMonth();

            if ($leaveStart->lte($probationRestrictionEnd)) {
                $monthsSinceStart = (int) $probationStart->copy()->startOfDay()->diffInMonths($leaveStart->copy()->startOfDay());
                $probationMaxLeaves = 0;

                if ($monthsSinceStart == 0) {
                    $probationMaxLeaves = 0;
                } elseif ($monthsSinceStart == 1) {
                    $probationMaxLeaves = 1;
                } else {
                    $probationMaxLeaves = 1.5; // Up to end of probation restriction period
                }

                $leavesTakenInRestriction = (float) LeaveRequest::where('employee_id', $employee->id)
                    ->where('leave_type_id', $request->leave_type_id)
                    ->whereIn('status', ['approved', 'pending'])
                    ->where('id', '!=', $leave->id)
                    ->whereDate('start_date', '<=', $probationRestrictionEnd)
                    ->whereDate('start_date', '>=', $probationStart)
                    ->sum('duration_days');

                $remainingProbationBalance = max(0, $probationMaxLeaves - $leavesTakenInRestriction);

                // Override the bucket balance during probation, because exact joining date
                // anniversaries (e.g. 15th) happen before the end-of-month cron runs.
                $remainingBalance = $remainingProbationBalance;
            }
        }

        if ($remainingBalance <= 0) {
            return $this->error("You have 0 days remaining for {$leaveType->name}. Please apply as LOP.", 422);
        }

        if ($durationDays > $remainingBalance) {
            $split = $this->splitLeaveIntoSlots($start, $end, $session1, $session2, $remainingBalance);
            $bucketDetails = $split['bucket'];
            $lopDetails = $split['lop'];

            $leave->update([
                'leave_type_id' => $request->leave_type_id,
                'start_date' => $bucketDetails['start_date'],
                'end_date' => $bucketDetails['end_date'],
                'session1' => $bucketDetails['session1'],
                'session2' => $bucketDetails['session2'],
                'duration_days' => $bucketDetails['duration_days'],
                'claim_salary' => $request->claim_salary ?? false,
                'document' => $documentPath,
                'reason' => $request->reason,
            ]);

            $lopLeave = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $lopLeaveType->id,
                'start_date' => $lopDetails['start_date'],
                'end_date' => $lopDetails['end_date'],
                'session1' => $lopDetails['session1'],
                'session2' => $lopDetails['session2'],
                'duration_days' => $lopDetails['duration_days'],
                'claim_salary' => false,
                'document' => $documentPath,
                'reason' => $request->reason . ' (Auto-split LOP)',
                'status' => 'pending',
            ]);

            return $this->success([
                'leave' => $leave,
                'lop_leave' => $lopLeave,
            ], "Leave request updated successfully. {$bucketDetails['duration_days']} day(s) allocated to {$leaveType->name} and {$lopDetails['duration_days']} day(s) created under LOP.");
        }

        $leave->update([
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'session1' => $session1,
            'session2' => $session2,
            'duration_days' => $durationDays,
            'claim_salary' => $request->claim_salary ?? false,
            'document' => $documentPath,
            'reason' => $request->reason,
        ]);

        return $this->success($leave, 'Leave request updated successfully');
    }

    public function destroyLeave($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leave = LeaveRequest::where('employee_id', $employee->id)->find($id);

        if (!$leave) {
            return $this->error('Leave request not found', 404);
        }

        if ($leave->status !== 'pending') {
            return $this->error('Only pending leave requests can be deleted.', 400);
        }

        $leave->delete();

        return $this->success(null, 'Leave request deleted successfully');
    }

    /**
     * Task Reports
     */
    public function taskReports(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $reports = TaskReport::where('employee_id', $user->id)->latest()->get();
        return $this->success($reports);
    }

    public function showTaskReport($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $report = TaskReport::where('employee_id', $employee->id)->find($id);

        if (!$report) {
            return $this->error('Task report not found', 404);
        }

        return $this->success($report);
    }

    public function storeTaskReport(Request $request): JsonResponse
    {
        $request->validate([
            'tasks_completed' => 'nullable|string',
            'plan_tomorrow' => 'nullable|string',
            'remarks' => 'nullable|string',
            'date' => 'nullable|date'
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $date = $request->date ?? Carbon::today()->toDateString();

        // Check if report already exists for this date
        $report = TaskReport::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date],
            [
                'tasks_completed' => $request->tasks_completed,
                'plan_tomorrow' => $request->plan_tomorrow,
                'remarks' => $request->remarks
            ]
        );

        return $this->success($report, 'Task report saved successfully', $report->wasRecentlyCreated ? 201 : 200);
    }

    public function updateTaskReport(Request $request, $id): JsonResponse
    {
        $request->validate([
            'tasks_completed' => 'nullable|string',
            'plan_tomorrow' => 'nullable|string',
            'remarks' => 'nullable|string'
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $report = TaskReport::where('employee_id', $employee->id)->find($id);

        if (!$report) {
            return $this->error('Task report not found', 404);
        }

        $report->update($request->only(['tasks_completed', 'plan_tomorrow', 'remarks']));

        return $this->success($report, 'Task report updated successfully');
    }

    public function destroyTaskReport($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $report = TaskReport::where('employee_id', $employee->id)->find($id);

        if (!$report) {
            return $this->error('Task report not found', 404);
        }

        $report->delete();

        return $this->success(null, 'Task report deleted successfully');
    }

    /**
     * WFH Requests
     */
    public function wfhRequests(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $requests = WfhRequest::where('employee_id', $employee->id)->latest()->get();
        return $this->success($requests);
    }

    public function showWfhRequest($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $request = WfhRequest::where('employee_id', $employee->id)->find($id);
        if (!$request) {
            return $this->error('WFH request not found', 404);
        }

        return $this->success($request);
    }

    public function storeWfhRequest(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        // Check for duplicate request on the same date
        $exists = WfhRequest::where('employee_id', $employee->id)
            ->whereDate('date', $request->date)
            ->exists();

        if ($exists) {
            return $this->error('You have already submitted a WFH request for this date.', 422);
        }

        $wfh = WfhRequest::create([
            'employee_id' => $employee->id,
            'date' => $request->date,
            'reason' => $request->reason,
            'notes' => $request->notes,
            'status' => 'pending'
        ]);

        return $this->success($wfh, 'WFH request submitted successfully', 201);
    }

    public function updateWfhRequest(Request $request, $id): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $wfh = WfhRequest::where('employee_id', $employee->id)->find($id);

        if (!$wfh) {
            return $this->error('WFH request not found', 404);
        }

        if ($wfh->status !== 'pending') {
            return $this->error('Only pending requests can be updated.', 400);
        }

        $wfh->update($request->only(['date', 'reason', 'notes']));

        return $this->success($wfh, 'WFH request updated successfully');
    }

    public function destroyWfhRequest($id): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $wfh = WfhRequest::where('employee_id', $employee->id)->find($id);

        if (!$wfh) {
            return $this->error('WFH request not found', 404);
        }

        if ($wfh->status !== 'pending') {
            return $this->error('Only pending requests can be deleted.', 400);
        }

        $wfh->delete();

        return $this->success(null, 'WFH request deleted successfully');
    }

    private function splitLeaveIntoSlots(Carbon $start, Carbon $end, ?string $session1, ?string $session2, float $remainingBalance): array
    {
        $holidays = Holiday::whereBetween('holiday_date', [
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
        ])
            ->pluck('holiday_date')
            ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
            ->toArray();

        $isNonWorkingDay = function (Carbon $date) use ($holidays) {
            return $date->isSunday() || in_array($date->format('Y-m-d'), $holidays);
        };

        $activeSlots = [];
        $currentDate = $start->copy();

        while ($currentDate->lte($end)) {
            if (!$isNonWorkingDay($currentDate)) {
                $dateStr = $currentDate->format('Y-m-d');

                if ($start->isSameDay($end)) {
                    if (empty($session1) && empty($session2)) {
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'morning'];
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'afternoon'];
                    } elseif ($session1 === 'morning' && $session2 === 'afternoon') {
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'morning'];
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'afternoon'];
                    } elseif ($session1 === 'afternoon' && $session2 === 'afternoon') {
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'afternoon'];
                    } elseif ($session1 === 'morning' && $session2 === 'morning') {
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'morning'];
                    } else {
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'morning'];
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'afternoon'];
                    }
                } elseif ($currentDate->isSameDay($start)) {
                    if ($session1 === 'afternoon') {
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'afternoon'];
                    } else {
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'morning'];
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'afternoon'];
                    }
                } elseif ($currentDate->isSameDay($end)) {
                    if ($session2 === 'morning') {
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'morning'];
                    } else {
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'morning'];
                        $activeSlots[] = ['date' => $dateStr, 'session' => 'afternoon'];
                    }
                } else {
                    $activeSlots[] = ['date' => $dateStr, 'session' => 'morning'];
                    $activeSlots[] = ['date' => $dateStr, 'session' => 'afternoon'];
                }
            }
            $currentDate->addDay();
        }

        $bucketSlotCount = (int) round($remainingBalance * 2);
        $bucketSlots = array_slice($activeSlots, 0, $bucketSlotCount);
        $lopSlots = array_slice($activeSlots, $bucketSlotCount);

        $getLeaveDetails = function (array $slots) {
            if (empty($slots)) {
                return null;
            }
            return [
                'start_date' => $slots[0]['date'],
                'end_date' => $slots[count($slots) - 1]['date'],
                'session1' => $slots[0]['session'],
                'session2' => $slots[count($slots) - 1]['session'],
                'duration_days' => count($slots) * 0.5,
            ];
        };

        return [
            'bucket' => $getLeaveDetails($bucketSlots),
            'lop' => $getLeaveDetails($lopSlots),
        ];
    }

    /**
     * Check if a requested leave session overlaps with existing non-rejected leaves for an employee.
     */
    private function hasOverlappingLeave(
        int $employeeId,
        string $startDateStr,
        string $endDateStr,
        ?string $session1,
        ?string $session2,
        ?int $excludeLeaveId = null
    ): bool {
        $query = LeaveRequest::where('employee_id', $employeeId)
            ->where('status', '!=', 'rejected')
            ->where(function ($q) use ($startDateStr, $endDateStr) {
                $q->where('start_date', '<=', $endDateStr)
                    ->where('end_date', '>=', $startDateStr);
            });

        if ($excludeLeaveId) {
            $query->where('id', '!=', $excludeLeaveId);
        }

        $existingLeaves = $query->get();

        if ($existingLeaves->isEmpty()) {
            return false;
        }

        $reqStart = Carbon::parse($startDateStr);
        $reqEnd = Carbon::parse($endDateStr);

        $getOccupiedSessions = function (Carbon $startDate, Carbon $endDate, ?string $s1, ?string $s2, Carbon $targetDate): array {
            if ($startDate->isSameDay($endDate)) {
                if (empty($s1) && empty($s2)) {
                    return ['morning', 'afternoon'];
                }
                if ($s1 === 'morning' && $s2 === 'afternoon') {
                    return ['morning', 'afternoon'];
                }
                if ($s1 === 'morning' && ($s2 === 'morning' || empty($s2))) {
                    return ['morning'];
                }
                if (empty($s1) && $s2 === 'morning') {
                    return ['morning'];
                }
                if ($s1 === 'afternoon' && ($s2 === 'afternoon' || empty($s2))) {
                    return ['afternoon'];
                }
                if (empty($s1) && $s2 === 'afternoon') {
                    return ['afternoon'];
                }
                return ['morning', 'afternoon'];
            } else {
                if ($targetDate->isSameDay($startDate)) {
                    return ($s1 === 'afternoon') ? ['afternoon'] : ['morning', 'afternoon'];
                }
                if ($targetDate->isSameDay($endDate)) {
                    return ($s2 === 'morning') ? ['morning'] : ['morning', 'afternoon'];
                }
                return ['morning', 'afternoon'];
            }
        };

        foreach ($existingLeaves as $existLeave) {
            $existStart = Carbon::parse($existLeave->start_date);
            $existEnd = Carbon::parse($existLeave->end_date);

            $overlapStart = $reqStart->gte($existStart) ? $reqStart->copy() : $existStart->copy();
            $overlapEnd = $reqEnd->lte($existEnd) ? $reqEnd->copy() : $existEnd->copy();

            $curr = $overlapStart->copy();
            while ($curr->lte($overlapEnd)) {
                $reqSessions = $getOccupiedSessions($reqStart, $reqEnd, $session1, $session2, $curr);
                $existSessions = $getOccupiedSessions($existStart, $existEnd, $existLeave->session1, $existLeave->session2, $curr);

                if (!empty(array_intersect($reqSessions, $existSessions))) {
                    return true;
                }

                $curr->addDay();
            }
        }

        return false;
    }
}
