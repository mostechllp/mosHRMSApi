<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaskApiController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $tasks = Task::with(['assignedTo', 'project'])->latest()->get();
        return $this->success($tasks);
    }

    /**
     * Get tasks for a specific project.
     */
    public function tasksByProject(int $projectId): JsonResponse
    {
        $tasks = Task::with(['assignedTo', 'project'])
            ->where('project_id', $projectId)
            ->latest()
            ->get();

        return $this->success([
            'project_id' => $projectId,
            'total' => $tasks->count(),
            'tasks' => $tasks,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'task_description' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'assigned_by' => 'nullable|string|max:255',
            'assigned_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_to' => 'required|array',
            'assigned_to.*' => 'exists:employees,id',
            'status' => 'required|in:assigned,in_progress,completed,on_hold'
        ]);

        $task = Task::create($validated);

        if ($request->has('assigned_to')) {
            $task->assignedTo()->attach($request->assigned_to);
        }

        return $this->success($task->load(['assignedTo', 'project']), 'Task created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task): JsonResponse
    {
        return $this->success($task->load(['assignedTo', 'project']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'task_description' => 'nullable|string',
            'project_id' => 'sometimes|required|exists:projects,id',
            'assigned_by' => 'nullable|string|max:255',
            'assigned_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_to' => 'nullable|array',
            'assigned_to.*' => 'exists:employees,id',
            'status' => 'required|in:assigned,in_progress,completed,on_hold'
        ]);

        $task->update($validated);

        if ($request->has('assigned_to')) {
            $task->assignedTo()->sync($request->assigned_to);
        }

        return $this->success($task->load(['assignedTo', 'project']), 'Task updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task): JsonResponse
    {
        $task->delete();
        return $this->success(null, 'Task deleted successfully');
    }
}
