<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceLog;
use App\Models\TaskReport;
use App\Models\WfhRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
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

        $employee = $user->employee;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $user->load('department', 'company', 'designation');

        $today = Carbon::today()->toDateString();

        // Attendance stats for today
        $attendance = AttendanceLog::where('userid', $user->id)
            ->whereDate('log_date', $today)
            ->select('punch_in', 'punch_out', 'punch_in_latitude', 'punch_in_longitude', 'punch_in_address', 'punch_out_latitude', 'punch_out_longitude', 'punch_out_address')
            ->first();

        // 30-day attendance history
        $from = Carbon::now()->subDays(30)->startOfDay();
        $to = Carbon::now()->endOfDay();
        $attendanceHistory = AttendanceLog::where('userid', $user->id)
            ->whereBetween('log_date', [$from, $to])
            ->select('log_date', 'punch_in', 'punch_out', 'punch_in_latitude', 'punch_in_longitude', 'punch_in_address', 'punch_out_latitude', 'punch_out_longitude', 'punch_out_address')
            ->orderByDesc('log_date')
            ->get();

        // Leave stats
        $totalLeavesTaken = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
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
            $canPunch = WfhRequest::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->where('status', 'Approved')
                ->exists();
        }

        return $this->success([
            'employee' => $employee,
            'today_attendance' => [
                'punched_in' => (bool) $attendance,
                'punched_out' => $attendance && $attendance->punch_out,
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
                ]
            ],
            'leave_stats' => [
                'total_taken' => (float) $totalLeavesTaken,
                'balance' => (float) $leaveBalance,
                'allocated' => (float) $employee->total_leaves_allocated,
            ],
            'attendance_history' => $attendanceHistory,
            'can_punch' => $canPunch,
            'pending_wfh_count' => WfhRequest::where('employee_id', $employee->id)->where('status', 'pending')->count(),
            'recent_leaves' => LeaveRequest::where('employee_id', $employee->id)->latest()->take(5)->get(),
        ]);
    }

    /**
     * Punch In
     */
    public function punchIn(Request $request): JsonResponse
    {
        $request->validate([
            'location.latitude' => 'nullable|numeric',
            'location.longitude' => 'nullable|numeric',
            'location.address' => 'nullable|string'
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
            'punch_in_latitude' => $request->input('location.latitude'),
            'punch_in_longitude' => $request->input('location.longitude'),
            'punch_in_address' => $request->input('location.address')
        ]);

        return $this->success($log, 'Punched in successfully.', 201);
    }

    /**
     * Punch Out
     */
    public function punchOut(Request $request): JsonResponse
    {
        $request->validate([
            'location.latitude' => 'nullable|numeric',
            'location.longitude' => 'nullable|numeric',
            'location.address' => 'nullable|string',
            'project_times' => 'nullable|array',
            'project_times.*.project_id' => 'required|exists:projects,id',
            'project_times.*.time_minutes' => 'required|integer|min:1',
        ]);

        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        // Get the active punch in record (could be from today or a previous day they forgot to punch out of)
        $log = AttendanceLog::where('userid', $user->id)
            ->whereNull('punch_out')
            ->orderBy('log_date', 'desc')
            ->first();

        if (!$log)
            return $this->error('You have no active punch in.', 400);

        $logDate = $log->log_date;
        $dayOfWeek = Carbon::parse($logDate)->format('l');

        $assignedProjectIds = $employee->projects()->pluck('projects.id')->toArray();

        if (count($assignedProjectIds) > 0) {
            $submittedProjectTimes = collect($request->project_times ?? []);

            $submittedProjectIds = $submittedProjectTimes->pluck('project_id')->toArray();
            $missingProjects = array_diff($assignedProjectIds, $submittedProjectIds);

            if (count($missingProjects) > 0) {

                return response()->json([
                    'message' => 'Please complete time entry for all assigned projects before punching out.',
                    'submittedProjectTimes' => $submittedProjectTimes,
                    'submitted_projects_count' => count($submittedProjectIds),
                    'missing_projects_count' => count($missingProjects),
                    'missing_project_ids' => array_values($missingProjects),
                ], 422);
            }

            $totalMinutes = $submittedProjectTimes->sum('time_minutes');

            $workingHour = \App\Models\WorkingHour::where('day', $dayOfWeek)->where('is_enabled', true)->first();
            $requiredMinutes = 0;
            if ($workingHour && $workingHour->start_time && $workingHour->end_time) {
                $requiredMinutes = Carbon::parse($workingHour->start_time)->diffInMinutes(Carbon::parse($workingHour->end_time));
            }

            if ($requiredMinutes > 0 && $totalMinutes < $requiredMinutes) {
                return $this->error("Total project time logged ($totalMinutes mins) is less than the required working hours ($requiredMinutes mins) for $dayOfWeek.", 422);
            }

            foreach ($submittedProjectTimes as $pt) {
                \App\Models\ProjectTimeLog::updateOrCreate(
                    ['employee_id' => $user->id, 'project_id' => $pt['project_id'], 'date' => $logDate],
                    ['time_taken_minutes' => $pt['time_minutes']]
                );
            }
        }

        $log->update([
            'punch_out' => Carbon::now(),
            'log_status' => 'OUT',
            'punch_out_latitude' => $request->input('location.latitude'),
            'punch_out_longitude' => $request->input('location.longitude'),
            'punch_out_address' => $request->input('location.address')
        ]);

        return $this->success($log, 'Punched out successfully.');
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
        return $this->success($leaves);
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

        $leaveTypesData = $leaveTypes->map(function ($leaveType) use ($employee, &$totalAllocated, &$totalTaken, &$totalBalance) {
            $allocated = (float) \App\Models\LeaveAllocation::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->sum('allocated_days');

            $taken = (float) LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'approved')
                ->sum('duration_days');

            $pending = (float) LeaveRequest::where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('status', 'pending')
                ->sum('duration_days');

            $balance = max(0, $allocated - $taken);

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
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|min:10',
            'claim_salary' => 'nullable|boolean',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'session1' => 'nullable|in:morning,afternoon',
            'session2' => 'nullable|in:morning,afternoon',
            'year' => 'nullable|integer',
        ]);

        $employee = Employee::find($request->employee_id);
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        // Check if there are overlapping leaves considering session1 and session2
        $hasOverlap = $this->hasOverlappingLeave(
            $employee->id,
            $request->start_date,
            $request->end_date,
            $request->input('session1'),
            $request->input('session2')
        );

        if ($hasOverlap) {
            return $this->error('Employee has already applied/taken leave on the selected date(s) and session(s).', 422);
        }

        $leaveType = LeaveType::find($request->leave_type_id);

        if ($leaveType && str_contains(strtolower($leaveType->name), 'sick') && !$request->hasFile('document')) {
            return $this->error('Medical certificate is required for sick leave', 422);
        }

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
        $session1 = $request->input('session1');
        $session2 = $request->input('session2');

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
                'start_date' => $start,
                'end_date' => $end,
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

        // Non-LOP category leave balance check - cumulative carry-forward across all years
        $allocated = (float) LeaveAllocation::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->sum('allocated_days');

        $leavesTaken = (float) LeaveRequest::where('employee_id', $employee->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('status', 'approved')
            ->sum('duration_days');

        $remainingBalance = max(0, $allocated - $leavesTaken);

        $probationStart = $employee->probation_start_date
            ? Carbon::parse($employee->probation_start_date)->startOfDay()
            : ($employee->joining_date ? Carbon::parse($employee->joining_date)->startOfDay() : null);

        $probationEnd = $employee->probation_end_date
            ? Carbon::parse($employee->probation_end_date)->endOfDay()
            : ($probationStart ? $probationStart->copy()->addMonths(3)->subDay()->endOfDay() : null);

        if ($probationStart && $probationEnd) {
            $leaveStart = Carbon::parse($request->start_date)->startOfDay();

            // Check if the leave falls within the 3-month probation period
            if ($leaveStart->lte($probationEnd) && $leaveStart->gte($probationStart)) {
                $monthIndex = (int) $probationStart->diffInMonths($leaveStart);

                if ($monthIndex === 0) {
                    // Month 1 of probation: 0 paid leaves allowed. Total will be LOP if leave is taken.
                    $remainingBalance = 0;
                } elseif ($monthIndex === 1) {
                    // Month 2 of probation: 1 leave allowed, rest is LOP
                    $mStart = $probationStart->copy()->addMonth();
                    $mEnd = $probationStart->copy()->addMonths(2)->subDay()->endOfDay();

                    $takenInMonth = (float) LeaveRequest::where('employee_id', $employee->id)
                        ->where('leave_type_id', $request->leave_type_id)
                        ->whereIn('status', ['approved', 'pending'])
                        ->whereDate('start_date', '<=', $mEnd)
                        ->whereDate('start_date', '>=', $mStart)
                        ->sum('duration_days');

                    $remainingBalance = min($remainingBalance, max(0, 1.0 - $takenInMonth));
                } else {
                    // Month 3 of probation: 1 leave allowed (deducted from earned balance), rest is LOP
                    $mStart = $probationStart->copy()->addMonths(2);
                    $mEnd = $probationEnd;

                    $takenInMonth = (float) LeaveRequest::where('employee_id', $employee->id)
                        ->where('leave_type_id', $request->leave_type_id)
                        ->whereIn('status', ['approved', 'pending'])
                        ->whereDate('start_date', '<=', $mEnd)
                        ->whereDate('start_date', '>=', $mStart)
                        ->sum('duration_days');

                    $remainingBalance = min($remainingBalance, max(0, 1.0 - $takenInMonth));
                }
            }
        }

        if ($durationDays > $remainingBalance) {
            $split = $this->splitLeaveIntoSlots($start, $end, $session1, $session2, $remainingBalance);
            $bucketDetails = $split['bucket'];
            $lopDetails = $split['lop'];

            $bucketLeave = null;
            if (!empty($bucketDetails) && ($bucketDetails['duration_days'] ?? 0) > 0) {
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
            }

            $lopLeave = null;
            if (!empty($lopDetails) && ($lopDetails['duration_days'] ?? 0) > 0) {
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
                    'reason' => $request->reason . ($bucketLeave ? ' (Auto-split LOP)' : ' (LOP)'),
                    'status' => 'pending',
                ]);
            }

            if ($bucketLeave && $lopLeave) {
                $msg = "Leave request submitted successfully. {$bucketDetails['duration_days']} day(s) deducted from {$leaveType->name} and {$lopDetails['duration_days']} day(s) created under LOP.";
            } elseif ($lopLeave) {
                $msg = "Leave request submitted successfully under LOP ({$lopDetails['duration_days']} day(s)).";
            } else {
                $msg = "Leave request submitted successfully.";
            }

            return $this->success(
                [
                    'leave' => $bucketLeave ?? $lopLeave,
                    'lop_leave' => $bucketLeave ? $lopLeave : null,
                ],
                $msg,
                201
            );
        }

        $leave = LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $start,
            'end_date' => $end,
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

    /**
     * Task Reports
     */
    public function taskReports(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user ? $user->employee : null;
        if (!$employee)
            return $this->error('Employee profile not found', 404);

        $reports = TaskReport::where('employee_id', $employee->id)->get()->orderBy('date', 'desc');
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
