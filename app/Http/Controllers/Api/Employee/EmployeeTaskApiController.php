<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Api\ApiController;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EmployeeTaskApiController extends ApiController
{
    /**
     * List all tasks assigned to the authenticated employee,
     * grouped by status sections.
     */
    public function index(): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee) {
            return $this->error('Employee profile not found', 404);
        }

        $employeeId = $employee->id;
        $today = now()->toDateString();

        $baseQuery = Task::with([
            'project:id,project_name',
            'assignedTo' => function ($q) use ($employeeId) {
                $q->where('employees.id', $employeeId)
                    ->select('employees.id')
                    ->withPivot('status');
            }
        ])->whereHas('assignedTo', fn($q) => $q->where('employees.id', $employeeId));

        $allTasks = (clone $baseQuery)->latest()->get()->map(fn($t) => $this->formatTask($t, $employeeId));
        $todayAssigned = (clone $baseQuery)->whereDate('assigned_date', $today)->get()->map(fn($t) => $this->formatTask($t, $employeeId));
        $inProgress = (clone $baseQuery)->whereHas('assignedTo', fn($q) => $q->where('employees.id', $employeeId)->where('task_employee.status', 'in_progress'))->get()->map(fn($t) => $this->formatTask($t, $employeeId));
        $completed = (clone $baseQuery)->whereHas('assignedTo', fn($q) => $q->where('employees.id', $employeeId)->where('task_employee.status', 'completed'))->get()->map(fn($t) => $this->formatTask($t, $employeeId));
        $onHold = (clone $baseQuery)->whereHas('assignedTo', fn($q) => $q->where('employees.id', $employeeId)->where('task_employee.status', 'on_hold'))->get()->map(fn($t) => $this->formatTask($t, $employeeId));

        return $this->success([
            'today_assigned_tasks' => $todayAssigned,
            'all_tasks' => $allTasks,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'on_hold' => $onHold,
        ]);
    }

    /**
     * Show a single task assigned to the authenticated employee.
     */
    public function show(int $taskId): JsonResponse
    {
        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee) {
            return $this->error('Employee profile not found', 404);
        }

        $employeeId = $employee->id;

        $task = Task::with([
            'project:id,project_name',
            'assignedTo' => function ($q) use ($employeeId) {
                $q->where('employees.id', $employeeId)
                    ->select('employees.id')
                    ->withPivot('status');
            }
        ])
            ->whereHas('assignedTo', fn($q) => $q->where('employees.id', $employeeId))
            ->find($taskId);

        if (!$task) {
            return $this->error('Task not found or not assigned to you', 404);
        }

        return $this->success($this->formatTask($task, $employeeId));
    }

    /**
     * Update the status of a task assigned to the authenticated employee.
     *
     * Accepted statuses: assigned | in_progress | completed | on_hold
     *
     * PATCH /employee/tasks/{taskId}/status
     * Body: { "status": "in_progress" }
     */
    public function updateStatus(Request $request, int $taskId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:assigned,in_progress,completed,on_hold',
        ]);

        $user = auth('api')->user();
        $employee = $user?->employee;

        if (!$employee) {
            return $this->error('Employee profile not found', 404);
        }

        $employeeId = $employee->id;

        // Verify the task exists and is assigned to this employee
        $task = Task::whereHas('assignedTo', fn($q) => $q->where('employees.id', $employeeId))
            ->find($taskId);

        if (!$task) {
            return $this->error('Task not found or not assigned to you', 404);
        }

        // Update the status on the pivot row
        $task->assignedTo()->updateExistingPivot($employeeId, [
            'status' => $request->status,
        ]);

        // Return the refreshed task
        $task->load([
            'project:id,project_name',
            'assignedTo' => function ($q) use ($employeeId) {
                $q->where('employees.id', $employeeId)
                    ->select('employees.id')
                    ->withPivot('status');
            }
        ]);

        return $this->success(
            $this->formatTask($task, $employeeId),
            'Task status updated successfully'
        );
    }

    /**
     * Format a Task for the employee, surfacing the pivot status.
     */
    private function formatTask(Task $task, int $employeeId): array
    {
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
}
