<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\ApiController;
use App\Models\AttendanceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class AttendanceRequestApiController extends ApiController
{
    /**
     * Display a listing of the employee's attendance requests.
     */
    public function index(): JsonResponse
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return $this->error('Employee record not found.', 404);
        }

        $requests = AttendanceRequest::where('employee_id', $employee->id)
            ->latest()
            ->get();

        return $this->success($requests);
    }

    /**
     * Store a new attendance request.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:early_check_in,late_check_in,missed_punch_in,missed_punch_out',
            'request_date' => 'required|date', // Expecting Y-m-d from API
            'request_time' => 'required|date_format:H:i',
            'reason' => 'required|string|max:1000',
        ]);

        $employee = auth()->user()->employee;
        if (!$employee) {
            return $this->error('Employee record not found.', 404);
        }

        // Check if date needs conversion if it's not Y-m-d, but usually API should send Y-m-d
        $date = $request->request_date;

        $attendanceRequest = AttendanceRequest::create([
            'employee_id' => $employee->id,
            'type' => $request->type,
            'request_date' => $date,
            'request_time' => $request->request_time,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return $this->success($attendanceRequest, 'Attendance request submitted successfully.', 201);
    }

    /**
     * Update an existing attendance request (only if it belongs to the employee and is still pending).
     */
    public function update(Request $request, AttendanceRequest $attendanceRequest): JsonResponse
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return $this->error('Employee record not found.', 404);
        }

        if ($attendanceRequest->employee_id !== $employee->id) {
            return $this->error('Unauthorized. This request does not belong to you.', 403);
        }

        if ($attendanceRequest->status !== 'pending') {
            return $this->error('Only pending requests can be edited.', 422);
        }

        $request->validate([
            'type'         => 'sometimes|string|in:early_check_in,late_check_in,missed_punch_in,missed_punch_out',
            'request_date' => 'sometimes|date',
            'request_time' => 'sometimes|date_format:H:i',
            'reason'       => 'sometimes|string|max:1000',
        ]);

        $attendanceRequest->update($request->only(['type', 'request_date', 'request_time', 'reason']));

        return $this->success($attendanceRequest, 'Attendance request updated successfully.');
    }

    /**
     * Delete an attendance request (only if it belongs to the employee and is still pending).
     */
    public function destroy(AttendanceRequest $attendanceRequest): JsonResponse
    {
        $employee = auth()->user()->employee;
        if (!$employee) {
            return $this->error('Employee record not found.', 404);
        }

        if ($attendanceRequest->employee_id !== $employee->id) {
            return $this->error('Unauthorized. This request does not belong to you.', 403);
        }

        if ($attendanceRequest->status !== 'pending') {
            return $this->error('Only pending requests can be deleted.', 422);
        }

        $attendanceRequest->delete();

        return $this->success(null, 'Attendance request deleted successfully.');
    }
}
