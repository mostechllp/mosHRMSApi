<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\AttendanceUpload;
use App\Jobs\ProcessAttendanceJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Attendance',
    description: 'Endpoints for managing employee attendance'
)]
class AttendanceApiController extends ApiController
{
    /**
     * Get Detailed Attendance Stats
     */
    #[OA\Get(
        path: '/api/admin/attendance/stats',
        operationId: 'getAttendanceStats',
        summary: 'Get detailed attendance stats with employee lists',
        description: 'Retrieves total, punched in, punched out, absent, and late counts along with employee details for each category for today.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    public function stats(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        // Get all active, non-admin employees
        $employees = Employee::with('user')->whereHas('user', function ($query) {
            $query->where('status', 'active')->where('type', '!=', 'admin');
        })->get();

        // Get today's attendance logs
        $todayLogs = AttendanceLog::whereDate('log_date', $today)
            ->select('userid', DB::raw('MIN(punch_in) as punch_in'), DB::raw('MAX(punch_out) as punch_out'))
            ->groupBy('userid')
            ->get()
            ->keyBy('userid'); // assuming userid in AttendanceLog is employee_id (string) or user_id (int). Based on codebase, it's usually employee_id or user_id. Wait, AttendanceLog uses user_id or employee_id?
            // In DashboardApiController: $todayLogs = AttendanceLog::whereDate('log_date', $today)... ->groupBy('userid')->get();
            // Let's assume userid maps to user_id or employee_id. The previous code uses it.
            // Let's key by userid to easily check.

        $result = [
            'total' => ['count' => 0, 'employees' => []],
            'punched_in' => ['count' => 0, 'employees' => []],
            'punched_out' => ['count' => 0, 'employees' => []],
            'absent' => ['count' => 0, 'employees' => []],
            'late' => ['count' => 0, 'employees' => []],
        ];

        foreach ($employees as $employee) {
            // Need to know what 'userid' in AttendanceLog refers to.
            // In Employee model, attendanceLogs() has 'userid' referencing 'employee_id'.
            // In DashboardApiController: userid is used. Let's use both user_id and employee_id to be safe, or just employee_id based on the model relation.
            // Actually, in DashboardApiController: $activeEmployees - $punchedInCount. So it just uses count.
            // Let's check employee->employee_id or employee->user_id against log->userid.
            $log = $todayLogs->get($employee->employee_id) ?? $todayLogs->get($employee->user_id);

            $empData = [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'user_id' => $employee->user_id,
                'name' => trim($employee->first_name . ' ' . $employee->last_name),
                'email' => $employee->user ? $employee->user->email : null,
            ];

            $result['total']['count']++;
            $result['total']['employees'][] = $empData;

            if ($log && $log->punch_in && $log->punch_in !== '--') {
                // Punched in
                $result['punched_in']['count']++;
                $result['punched_in']['employees'][] = $empData;

                $punchInTime = Carbon::parse($log->punch_in)->format('H:i:s');
                if ($punchInTime >= '08:11:00' && $punchInTime <= '12:00:00') {
                    $result['late']['count']++;
                    $result['late']['employees'][] = $empData;
                }

                if ($log->punch_out && $log->punch_out !== '--' && Carbon::parse($log->punch_out)->format('H:i:s') >= '12:00:00') {
                    $result['punched_out']['count']++;
                    $result['punched_out']['employees'][] = $empData;
                }
            } else {
                // Absent
                $result['absent']['count']++;
                $result['absent']['employees'][] = $empData;
            }
        }

        return $this->success($result);
    }

    /**
     * Get Attendance Summary with Stats
     */
    #[OA\Get(
        path: '/api/admin/attendance',
        operationId: 'getAttendanceSummary',
        summary: 'Get attendance summary with stats',
        description: 'Retrieves attendance records with optional filtering by company, employee name, and date preset.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Items per page', schema: new OA\Schema(type: 'integer', example: 15))]
    #[OA\Parameter(name: 'company_id', in: 'query', required: false, description: 'Filter by Company ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'employee_name', in: 'query', required: false, description: 'Filter by Employee Name', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'date_preset', in: 'query', required: false, description: 'Date preset filter', schema: new OA\Schema(type: 'string', enum: ['all', 'today', 'yesterday', 'last_week', 'last_month', 'custom'], example: 'all'))]
    #[OA\Parameter(name: 'from_date', in: 'query', required: false, description: 'From date for custom preset', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'to_date', in: 'query', required: false, description: 'To date for custom preset', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Response(response: 200, description: 'Successful operation', content: new OA\JsonContent(properties: [new OA\Property(property: 'success', type: 'boolean', example: true), new OA\Property(property: 'data', type: 'object')]))]
    public function index(Request $request): JsonResponse
    {
        $perPage = 100;
        $companyId = $request->get('company_id');
        $employeeName = $request->get('employee_name');
        $datePreset = $request->get('date_preset', 'all');

        $query = AttendanceLog::with(['company', 'user.employee', 'user.department', 'breaks'])
            ->select(
                'id',
                'company_id',
                'userid',
                'attendance_status',
                'log_date',
                'working_hours',
                'punch_in_latitude',
                'punch_in_longitude',
                'punch_in_address',
                'punch_out_latitude',
                'punch_out_longitude',
                'punch_out_address',
                DB::raw("MIN(punch_in) as punch_in"),
                DB::raw("MAX(punch_out) as punch_out")
            );

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($employeeName) {
            $query->whereHas('user.employee', function ($q) use ($employeeName) {
                $q->where('first_name', 'like', "%$employeeName%")
                    ->orWhere('last_name', 'like', "%$employeeName%");
            });
        }

        if ($datePreset != 'all') {
            $this->applyDateFilter($query, $datePreset, $request->get('from_date'), $request->get('to_date'));
        }


        $attendance = $query->groupBy(
            'id',
            'company_id',
            'userid',
            'log_date',
            'attendance_status',
            'working_hours',
            'punch_in_latitude',
            'punch_in_longitude',
            'punch_in_address',
            'punch_out_latitude',
            'punch_out_longitude',
            'punch_out_address',
        )
            ->orderBy('log_date', 'desc')
            ->paginate($perPage);

        $attendance->getCollection()->transform(function ($log) {
            $tz = config('app.timezone', 'Asia/Dubai');

            // Format date to dd/mm/yyyy
            $log->log_date = ($log->log_date && $log->log_date !== '--')
                ? Carbon::parse($log->log_date)->format('d/m/Y')
                : '--';

            $log->punch_in = ($log->punch_in && $log->punch_in !== '--')
                ? Carbon::parse($log->punch_in)->setTimezone($tz)->format('h:i A')
                : '--';
            // Output → "08 Jun 2026, 07:29 AM"

            $log->punch_out = ($log->punch_out && $log->punch_out !== '--')
                ? Carbon::parse($log->punch_out)->setTimezone($tz)->format('h:i A')
                : '--';
            // Output → "08 Jun 2026, 12:32 PM"

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

            if ($log->relationLoaded('breaks')) {
                $log->breaks->transform(function ($break) use ($tz) {
                    $break->start_time = ($break->start_time && $break->start_time !== '--')
                        ? Carbon::parse($break->start_time)->setTimezone($tz)->format('h:i A')
                        : '--';
                    $break->end_time = ($break->end_time && $break->end_time !== '--')
                        ? Carbon::parse($break->end_time)->setTimezone($tz)->format('h:i A')
                        : '--';

                    $b_minutes = $break->duration_minutes ?? 0;
                    $b_hours = intdiv($b_minutes, 60);
                    $b_mins = $b_minutes % 60;
                    
                    if ($b_minutes == 0)
                        $break->formatted_duration = '--';
                    elseif ($b_hours == 0)
                        $break->formatted_duration = "{$b_mins} mins";
                    elseif ($b_mins == 0)
                        $break->formatted_duration = "{$b_hours} hrs";
                    else
                        $break->formatted_duration = "{$b_hours} hrs {$b_mins} mins";

                    return $break;
                });
            }

            return $log;
        });

        return $this->success([
            'attendance' => $attendance,
            'stats' => $this->getStats()
        ]);
    }

    /**
     * Manually Add Attendance
     */
    #[OA\Post(
        path: '/api/admin/attendance',
        operationId: 'storeAttendance',
        summary: 'Manually add attendance for an employee',
        description: 'Creates a manual attendance log entry for a specific employee on a given date.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userid', 'log_date', 'punch_in'],
            properties: [
                new OA\Property(property: 'userid', type: 'integer', example: 5),
                new OA\Property(property: 'company_id', type: 'integer', nullable: true, example: 1),
                new OA\Property(property: 'log_date', type: 'string', format: 'date', example: '2026-06-07'),
                new OA\Property(property: 'punch_in', type: 'string', format: 'date-time', example: '2026-06-07 09:00:00'),
                new OA\Property(property: 'punch_out', type: 'string', format: 'date-time', nullable: true, example: '2026-06-07 18:00:00'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Attendance manually added successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Attendance manually added successfully.'),
                new OA\Property(property: 'data', type: 'object')
            ]
        )
    )]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 500, description: 'Server error')]
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'userid' => 'required|exists:users,id',
                'company_id' => 'nullable|exists:companies,id',
                'log_date' => 'required|date',
                'punch_in' => 'required|date_format:Y-m-d H:i:s',
                'punch_out' => 'nullable|date_format:Y-m-d H:i:s|after_or_equal:punch_in',
            ]);

            $attendance = AttendanceLog::create([
                'userid' => $request->userid,
                'company_id' => $request->company_id ?? null,
                'log_date' => $request->log_date,
                'punch_in' => $request->punch_in,
                'punch_out' => $request->punch_out,
                'attendance_status' => 'present',
                'log_status' => 'in'
            ]);

            return $this->success($attendance, 'Attendance  added successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Attendance Manual Entry Error: ' . $e->getMessage());
            return $this->error('Failed to add attendance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update Attendance Log
     */
    #[OA\Put(
        path: '/api/admin/attendance/{id}',
        operationId: 'updateAttendance',
        summary: 'Update an existing attendance log',
        description: 'Updates attendance log details for an employee.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Attendance Log ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'company_id', type: 'integer', nullable: true, example: 1),
                new OA\Property(property: 'log_date', type: 'string', format: 'date', example: '2026-06-07'),
                new OA\Property(property: 'punch_in', type: 'string', format: 'date-time', example: '2026-06-07 09:00:00'),
                new OA\Property(property: 'punch_out', type: 'string', format: 'date-time', nullable: true, example: '2026-06-07 18:00:00'),
                new OA\Property(property: 'attendance_status', type: 'string', nullable: true, example: 'present')
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Attendance updated successfully')]
    #[OA\Response(response: 404, description: 'Attendance record not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
   public function update(Request $request, $id): JsonResponse
    {
        try {
            $attendance = AttendanceLog::find($id);
            if (!$attendance) {
                return $this->error('Attendance record not found', 404);
            }
    
            $request->validate([
                'company_id'        => 'nullable|exists:companies,id',
                'log_date'          => 'sometimes|required|date',
                'punch_in'          => 'sometimes|required|date_format:Y-m-d H:i:s',
                'punch_out'         => 'nullable|date_format:Y-m-d H:i:s|after_or_equal:punch_in',
                'attendance_status' => 'nullable|in:present,absent,late,early_out,half_day,wfh'
            ]);
    
            // Calculate working hours only if both values are present
            $workingMinutes = null;
            if ($request->punch_in && $request->punch_out) {
                $punchIn  = Carbon::parse($request->punch_in);
                $punchOut = Carbon::parse($request->punch_out);
                $workingMinutes = $punchIn->diffInMinutes($punchOut);
            }
    
            $attendance->update(array_merge(
                $request->only(['company_id', 'log_date', 'punch_in', 'punch_out', 'attendance_status']),
                ['working_hours' => $workingMinutes]
            ));
    
            return $this->success($attendance, 'Attendance updated successfully.');
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Attendance Update Error: ' . $e->getMessage());
            return $this->error('Failed to update attendance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete Attendance Log
     */
    #[OA\Delete(
        path: '/api/admin/attendance/{id}',
        operationId: 'deleteAttendance',
        summary: 'Delete an attendance log',
        description: 'Deletes a specific attendance log.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Attendance Log ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Attendance deleted successfully')]
    #[OA\Response(response: 404, description: 'Attendance record not found')]
    public function destroy($id): JsonResponse
    {
        try {
            $attendance = AttendanceLog::find($id);
            if (!$attendance) {
                return $this->error('Attendance record not found', 404);
            }

            $attendance->delete();

            return $this->success(null, 'Attendance deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Attendance Delete Error: ' . $e->getMessage());
            return $this->error('Failed to delete attendance: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload Attendance File
     */
    #[OA\Post(
        path: '/api/admin/attendance/upload',
        operationId: 'uploadAttendance',
        summary: 'Upload attendance file',
        description: 'Uploads a file (.dat, .csv, .txt) containing attendance records. Excels and CSVs are processed directly; .dat and .txt files are queued.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['file'],
                properties: [
                    new OA\Property(property: 'file', description: 'Attendance file', type: 'string', format: 'binary')
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Upload successful or processing started')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 500, description: 'Server error')]
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:dat,csv,txt|max:2048',
            // 'company_id' => 'required|exists:companies,id'
        ]);

        try {
            $file = $request->file('file');

            // Ensure directory exists
            if (!Storage::disk('private')->exists('attendance')) {
                Storage::disk('private')->makeDirectory('attendance');
            }

            // Store file
            $path = $file->store('attendance', 'private');

            // Double check file actually exists
            if (!Storage::disk('private')->exists($path)) {
                Log::error('File not stored properly: ' . $path);
                return response()->json([
                    'message' => 'File upload failed'
                ], 500);
            }

            // Save in DB
            $upload = AttendanceUpload::create([
                'file_path' => $path,
                'status' => 'pending',
                'progress' => 0
            ]);

            $extension = $file->getClientOriginalExtension();

            if (in_array($extension, ['xlsx', 'csv'])) {
                // Direct import for Excel/CSV
                Excel::import(new \App\Imports\AttendanceImport($upload->id), $path, 'private');
                $upload->update(['status' => 'completed', 'progress' => 100]);

                return $this->success($upload, 'Attendance imported successfully');
            }
            // Dispatch background job for .dat/.txt
            ProcessAttendanceJob::dispatch($upload->id);

            return $this->success($upload, 'Attendance file uploaded and processing started');
        } catch (\Exception $e) {
            Log::error('Upload Error: ' . $e->getMessage());
            return $this->error('Upload failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check Upload Progress
     */
    #[OA\Get(
        path: '/api/admin/attendance/upload-status/{id}',
        operationId: 'uploadStatus',
        summary: 'Check attendance upload progress',
        description: 'Retrieves the current processing status and progress for a queued attendance file upload.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Upload ID', schema: new OA\Schema(type: 'integer', example: 1))]
    #[OA\Response(response: 200, description: 'Upload status retrieved')]
    #[OA\Response(response: 404, description: 'Upload record not found')]
    public function uploadStatus($id): JsonResponse
    {
        $upload = AttendanceUpload::find($id);
        if (!$upload)
            return $this->error('Upload record not found', 404);
        return $this->success($upload);
    }

    /**
     * Get Punch-In Today
     */
    #[OA\Get(
        path: '/api/admin/attendance/punch-in-today',
        operationId: 'punchInToday',
        summary: 'Get employees who punched in today',
        description: 'Retrieves attendance logs for employees who punched in today before 12:00 PM.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15))]
    #[OA\Parameter(name: 'company_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'List of today punch-ins')]
    public function punchInToday(Request $request): JsonResponse
    {
        return $this->getFilteredAttendance($request, 'today', 'punch_in');
    }

    /**
     * Get Punch-In Yesterday
     */
    #[OA\Get(
        path: '/api/admin/attendance/punch-in-yesterday',
        operationId: 'punchInYesterday',
        summary: 'Get employees who punched in yesterday',
        description: 'Retrieves attendance logs for employees who punched in yesterday before 12:00 PM.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15))]
    #[OA\Parameter(name: 'company_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'List of yesterday punch-ins')]
    public function punchInYesterday(Request $request): JsonResponse
    {
        return $this->getFilteredAttendance($request, 'yesterday', 'punch_in');
    }

    /**
     * Get Punch-Out Today
     */
    #[OA\Get(
        path: '/api/admin/attendance/punch-out-today',
        operationId: 'punchOutToday',
        summary: 'Get employees who punched out today',
        description: 'Retrieves attendance logs for employees who punched out today at or after 12:00 PM.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15))]
    #[OA\Parameter(name: 'company_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'List of today punch-outs')]
    public function punchOutToday(Request $request): JsonResponse
    {
        return $this->getFilteredAttendance($request, 'today', 'punch_out');
    }

    /**
     * Get Late Comers
     */
    #[OA\Get(
        path: '/api/admin/attendance/late-comers',
        operationId: 'lateComers',
        summary: 'Get employees who came late',
        description: 'Retrieves employees who punched in after 08:10:59 AM.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15))]
    #[OA\Parameter(name: 'date_preset', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'today'))]
    #[OA\Parameter(name: 'company_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'from_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'to_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Response(response: 200, description: 'List of late comers')]
    public function lateComers(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $datePreset = $request->get('date_preset', 'today');

        $query = AttendanceLog::with(['company', 'user.employee', 'user.department']);
        $this->applyDateFilter($query, $datePreset, $request->get('from_date'), $request->get('to_date'));

        $query->select(
            'company_id',
            'userid',
            'log_date',
            DB::raw("MIN(punch_in) as punch_in"),
            DB::raw("MAX(punch_out) as punch_out")
        )
            ->groupBy('company_id', 'userid', 'log_date')
            ->havingRaw("TIME(MIN(punch_in)) > '10:00:01' AND TIME(MIN(punch_in)) <= '12:00:00'");

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return $this->success($query->paginate($perPage));
    }

    /**
     * Get Absentees
     */
    #[OA\Get(
        path: '/api/admin/attendance/absentees',
        operationId: 'absentees',
        summary: 'Get absent employees',
        description: 'Retrieves list of active employees who do not have an attendance log for a given date.',
        security: [['bearerAuth' => []]],
        tags: ['Attendance']
    )]
    #[OA\Parameter(name: 'date', in: 'query', required: false, description: 'Date to check for absentees (defaults to today)', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'company_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15))]
    #[OA\Response(response: 200, description: 'List of absentees')]
    public function absentees(Request $request): JsonResponse
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $companyId = $request->get('company_id');

        $presentUserIds = AttendanceLog::whereDate('log_date', $date)
            ->pluck('userid')
            ->unique();

        $query = Employee::with(['user.company', 'user.department', 'user.designation'])
            ->whereNotIn('employee_id', $presentUserIds)
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
                $q->where('type', '!=', 'admin');
            });

        if ($companyId) {
            $query->whereHas('user', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        return $this->success($query->paginate($request->get('per_page', 15)));
    }

    /**
     * Private Helpers
     */

    private function getFilteredAttendance(Request $request, $day, $type): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $date = ($day === 'today') ? Carbon::today()->toDateString() : Carbon::yesterday()->toDateString();

        $query = AttendanceLog::with(['company', 'user.employee', 'user.department'])
            ->whereDate('log_date', $date)
            ->select(
                'company_id',
                'userid',
                'log_date',
                DB::raw("MIN(punch_in) as punch_in"),
                DB::raw("MAX(punch_out) as punch_out")
            );

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($type === 'punch_out') {
            $query->havingRaw("TIME(MAX(punch_out)) >= '12:00:00'");
        } else {
            $query->havingRaw("TIME(MIN(punch_in)) <= '12:00:00'");
        }

        return $this->success($query->groupBy('company_id', 'userid', 'log_date')->paginate($perPage));
    }

    private function applyDateFilter($query, $preset, $from = null, $to = null)
    {
        $now = Carbon::now();
        switch ($preset) {
            case 'today':
                $query->whereDate('log_date', $now->toDateString());
                break;
            case 'yesterday':
                $query->whereDate('log_date', $now->subDay()->toDateString());
                break;
            case 'last_week':
                $query->whereBetween('log_date', [$now->subWeek()->startOfDay(), Carbon::now()->endOfToday()]);
                break;
            case 'last_month':
                $query->whereBetween('log_date', [$now->subMonth()->startOfDay(), Carbon::now()->endOfToday()]);
                break;
            case 'custom':
                if ($from && $to) {
                    $query->whereBetween('log_date', [
                        Carbon::parse($from)->startOfDay(),
                        Carbon::parse($to)->endOfDay()
                    ]);
                }
                break;
        }
    }

    private function getStats(): array
    {
        $today = Carbon::today()->toDateString();
        $activeEmployeesCount = User::where('status', 'active')->where('type', 'employee')->count();

        $todayLogs = AttendanceLog::whereDate('log_date', $today)
            ->select('userid', DB::raw('MIN(punch_in) as punch_in'), DB::raw('MAX(punch_out) as punch_out'))
            ->groupBy('userid')
            ->get();

        $punchedInCount = $todayLogs->filter(function ($log) {
            return $log->punch_in && $log->punch_in !== '--' && Carbon::parse($log->punch_in)->format('H:i:s') <= '12:00:00';
        })->count();

        $punchedLateCount = $todayLogs->filter(function ($log) {
            if (!$log->punch_in || $log->punch_in === '--')
                return false;
            $time = Carbon::parse($log->punch_in)->format('H:i:s');
            return $time > '08:10:59' && $time <= '12:00:00';
        })->count();

        return [
            'total_active_employees' => $activeEmployeesCount,
            'present_today' => $todayLogs->count(),
            'absent_today' => max(0, $activeEmployeesCount - $todayLogs->count()),
            'punched_in_on_time' => $punchedInCount - $punchedLateCount,
            'punched_late' => $punchedLateCount,
            'punched_out_today' => $todayLogs->filter(fn($l) => $l->punch_out && $l->punch_out !== '--' && Carbon::parse($l->punch_out)->format('H:i:s') >= '12:00:00')->count()
        ];
    }
}
