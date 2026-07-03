<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\TaskReport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class TaskReportApiController extends ApiController
{
    /**
     * Display a listing of task reports (admin CRUD view).
     * Searchable by date, date range, employee_id (user id), and employee name.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage    = $request->get('per_page', 15);
        $employeeId = $request->get('employee_id');
        $date       = $request->get('date');
        $fromDate   = $request->get('from_date');
        $toDate     = $request->get('to_date');
        $search     = $request->get('search');

        $query = TaskReport::with(['user.employee']);

        // Filter by specific user id (users.id)
        if ($employeeId && $employeeId !== 'all') {
            $query->where('employee_id', $employeeId);
        }

        // Filter by date or date range
        if ($date) {
            $query->whereDate('date', $date);
        } elseif ($fromDate && $toDate) {
            $query->whereBetween('date', [$fromDate, $toDate]);
        }

        // Search by employee name (first_name / last_name) or employee_id string
        if ($search) {
            $query->whereHas('user.employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $reports = $query->latest('date')->paginate($perPage);

        return $this->success($reports);
    }

    /**
     * Store a newly created task report.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id'     => 'required|exists:users,id',
            'date'            => 'required|date',
            'tasks_completed' => 'required|string',
            'plan_tomorrow'   => 'nullable|string',
            'pending_tasks'   => 'nullable|string',
            'remarks'         => 'nullable|string',
        ]);

        $report = TaskReport::updateOrCreate(
            ['employee_id' => $request->employee_id, 'date' => $request->date],
            $request->only(['tasks_completed', 'pending_tasks', 'plan_tomorrow', 'remarks'])
        );

        return $this->success(
            $report->load('user.employee'),
            'Task report saved successfully',
            $report->wasRecentlyCreated ? 201 : 200
        );
    }

    /**
     * Display the specified task report.
     */
    public function show(TaskReport $taskReport): JsonResponse
    {
        return $this->success($taskReport->load(['user.employee']));
    }

    /**
     * Update the specified task report.
     */
    public function update(Request $request, TaskReport $taskReport): JsonResponse
    {
        $request->validate([
            'tasks_completed' => 'nullable|string',
            'pending_tasks'   => 'nullable|string',
            'plan_tomorrow'   => 'nullable|string',
            'remarks'         => 'nullable|string',
            'date'            => 'nullable|date',
            'employee_id'     => 'nullable|exists:users,id',
        ]);

        $taskReport->update($request->only([
            'tasks_completed', 'pending_tasks', 'plan_tomorrow', 'remarks', 'date', 'employee_id',
        ]));

        return $this->success($taskReport->load('user.employee'), 'Task report updated successfully');
    }

    /**
     * Remove the specified task report from storage.
     */
    public function destroy(TaskReport $taskReport): JsonResponse
    {
        $taskReport->delete();
        return $this->success(null, 'Task report deleted successfully');
    }
}
