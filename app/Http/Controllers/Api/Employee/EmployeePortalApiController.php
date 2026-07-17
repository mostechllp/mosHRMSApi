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

        $user->load('department', 'company', 'designation');

        $today = Carbon::today()->toDateString();

        // Attendance stats for today
        $attendance = AttendanceLog::where('userid', $user->id)
            ->whereDate('log_date', $today)
            ->select('punch_in', 'punch_out', 'punch_in_latitude', 'punch_in_longitude', 'punch_in_address', 'punch_out_latitude', 'punch_out_longitude', 'punch_out_address', 'working_hours')
            ->first();

        // ↓ Store raw punch_out status BEFORE formatting
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

        $leaveBalance = $employee->total_leaves_allocated - $totalLeavesTaken;

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

        return $this->success([
            'employee' => $user->employee,
            'today_attendance' => [
                'punched_in' => (bool) $attendance,
                'punched_out' => $isPunchedOut,
                'punch_in_time' => $attendance ? $attendance->punch_in : null,
                'punch_out_time' => $attendance ? $attendance->punch_out : null,
                'punch_in_location' => [          // ← ADD THIS
                    'latitude' => $attendance ? $attendance->punch_in_latitude : null,
                    'longitude' => $attendance ? $attendance->punch_in_longitude : null,
                    'address' => $attendance ? $attendance->punch_in_address : null
                ],
                'punch_out_location' => [          // ← ADD THIS
                    'latitude' => $attendance ? $attendance->punch_out_latitude : null,
                    'longitude' => $attendance ? $attendance->punch_out_longitude : null,
                    'address' => $attendance ? $attendance->punch_out_address : null
                ],
                'working_hours' => $attendance ? $attendance->working_hours : '--'
            ],
            'leave_stats' => [
                'total_taken' => (float) $totalLeavesTaken,
                'balance' => (float) $leaveBalance,
                'allocated' => (float) $employee->total_leaves_allocated,
            ],
            'attendance_history' => $attendanceHistory,
            'can_punch' => $canPunch,
            'pending_wfh_count' => WfhRequest::where('employee_id', $user->employee->id)->where('status', 'pending')->count(),
            'recent_leaves' => LeaveRequest::where('employee_id', $user->employee->id)->latest()->take(5)->get(),
            'tasks' => [
                'today_assigned_tasks' => $todayAssignedTasks,
                'all_tasks' => $allTasks,
                'in_progress' => $tasksInProgress,
                'completed' => $tasksCompleted,
                'on_hold' => $tasksOnHold,
            ],
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
        $workingMinutes = $punchIn->diffInMinutes($punchOutTime);

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
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $user = auth('api')->user();
        if (!$user || !$user->employee) {
            return $this->error('Employee profile not found', 404);
        }

        $date = $request->input('date', Carbon::today()->toDateString());

        $log = AttendanceLog::with('breaks')
            ->where('userid', $user->id)
            ->whereDate('log_date', $date)
            ->first();

        if (!$log) {
            return $this->success([
                'date' => $date,
                'breaks' => [],
                'total_break_minutes' => 0
            ], 'No attendance log found for the given date.');
        }

        return $this->success([
            'date' => $date,
            'breaks' => $log->breaks,
            'total_break_minutes' => $log->breaks->sum('duration_minutes')
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
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10',
            'claim_salary' => 'nullable|boolean',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'session1' => 'nullable|in:morning,afternoon',
            'session2' => 'nullable|in:morning,afternoon',
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leaveType = LeaveType::find($request->leave_type_id);

        // Check for sick leave document
        if (str_contains(strtolower($leaveType->name), 'sick') && !$request->hasFile('document')) {
            return $this->error('Medical certificate is required for sick leave', 422);
        }

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $session1 = $request->session1;
        $session2 = $request->session2;

        $durationDays = 0.0;
        $currentDate = $start->copy();

        while ($currentDate->lte($end)) {
            if ($currentDate->isSunday()) {
                $currentDate->addDay();
                continue;
            }

            if ($currentDate->isSameDay($start) && $currentDate->isSameDay($end)) {
                if ($session1 === 'morning' && $session2 === 'afternoon') {
                    $durationDays += 1.0;
                } else {
                    $durationDays += 0.5;
                }
            } elseif ($currentDate->isSameDay($start)) {
                $durationDays += ($session1 === 'morning') ? 1.0 : 0.5;
            } elseif ($currentDate->isSameDay($end)) {
                $durationDays += ($session2 === 'afternoon') ? 1.0 : 0.5;
            } else {
                $durationDays += 1.0;
            }

            $currentDate->addDay();
        }

        // Balance check
        $currentYear = date('Y');
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        $allocated = $allocation ? (float) $allocation->allocated_days : 0;

        $leavesTaken = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->sum('duration_days');

        $remainingBalance = $allocated - $leavesTaken;

        if ($durationDays > $remainingBalance) {
            return $this->error("Insufficient leave balance. You have only $remainingBalance days remaining.", 422);
        }

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leaves/documents', 'public');
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
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $leave = LeaveRequest::where('employee_id', $employee->id)->find($id);

        if (!$leave) {
            return $this->error('Leave request not found', 404);
        }

        if ($leave->status !== 'pending') {
            return $this->error('Only pending leave requests can be updated.', 400);
        }

        $leaveType = LeaveType::find($request->leave_type_id);

        if (str_contains(strtolower($leaveType->name), 'sick') && !$request->hasFile('document') && !$leave->document) {
            return $this->error('Medical certificate is required for sick leave', 422);
        }

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $session1 = $request->session1;
        $session2 = $request->session2;

        $durationDays = 0.0;
        $currentDate = $start->copy();

        while ($currentDate->lte($end)) {
            if ($currentDate->isSunday()) {
                $currentDate->addDay();
                continue;
            }

            if ($currentDate->isSameDay($start) && $currentDate->isSameDay($end)) {
                if ($session1 === 'morning' && $session2 === 'afternoon') {
                    $durationDays += 1.0;
                } else {
                    $durationDays += 0.5;
                }
            } elseif ($currentDate->isSameDay($start)) {
                $durationDays += ($session1 === 'morning') ? 1.0 : 0.5;
            } elseif ($currentDate->isSameDay($end)) {
                $durationDays += ($session2 === 'afternoon') ? 1.0 : 0.5;
            } else {
                $durationDays += 1.0;
            }

            $currentDate->addDay();
        }

        $currentYear = date('Y');
        $allocation = LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        $allocated = $allocation ? (float) $allocation->allocated_days : 0;

        $leavesTaken = LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'approved')
            ->whereYear('start_date', $currentYear)
            ->sum('duration_days');

        $remainingBalance = $allocated - $leavesTaken;

        if ($durationDays > $remainingBalance) {
            return $this->error("Insufficient leave balance. You have only $remainingBalance days remaining.", 422);
        }

        $documentPath = $leave->document;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('leaves/documents', 'public');
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
}
