<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectApiController extends ApiController
{
    public const STATUSES = [
        'Active',
        'Completed',
        'On-hold',
        'In-progress',
        'Proposal Created',
        'Proposal Sent',
        'Proposal Approved',
        'Quotation Created',
        'Quotation Sent',
        'Quotation Approved',
        'Invoice Created',
        'Invoice Sent',
        'Invoice Received',
        'Payment Pending',
        'Payment Received',
        'Project Started',
        'Project In Progress',
        'Project Completed',
    ];

    /**
     * Get the list of available project statuses.
     */
    public function getStatuses(): JsonResponse
    {
        return $this->success(self::STATUSES);
    }

    /**
     * Display a listing of the projects.
     */
    public function index(): JsonResponse
    {
        $projects = Project::with('emails')->latest()->get();
        return $this->success($projects);
    }

    /**
     * Get eligible project managers and team leads.
     */
    public function getEligibleManagers(): JsonResponse
    {
        $allowedRoles = [
            'Super Admin',
            'Admin',
            'Subadmin',
            'HR Manager',
            'BIM Manager',
            'BIM Assistant Manager',
            'BIM Team Lead',
            'BIM Coordinator'
        ];

        $employees = \App\Models\Employee::whereHas('user.role', function ($query) use ($allowedRoles) {
            $query->whereIn('name', $allowedRoles);
        })->get()->map(function ($employee) {
            return [
                'id' => $employee->id,
                'user_id' => $employee->user_id,
                'employee_id' => $employee->employee_id,
                'full_name' => trim($employee->first_name . ' ' . $employee->last_name),
            ];
        });

        return $this->success($employees);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_name' => 'nullable|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'website_live_date' => 'nullable|date',
            'client_contacted_date' => 'nullable|date',
            'domain_name' => 'nullable|string|max:255',
            'domain_purchased_date' => 'nullable|date',
            'website_url' => 'nullable|string|max:255',
            'domain_expiry_date' => 'nullable|date',
            'domain_purchased_from' => 'nullable|string|max:255',
            'is_email_purchased' => 'nullable|boolean',
            'status' => 'nullable|in:' . implode(',', self::STATUSES),
            'project_manager_id' => 'nullable|exists:employees,id',
            'team_lead_id' => 'nullable|exists:employees,id',
            'emails' => 'nullable|array',
            'emails.*.email_name' => 'nullable|string|max:255',
            'emails.*.purchase_date' => 'nullable|date',
            'emails.*.expiry_date' => 'nullable|date',
            'special_dates_name.*' => 'nullable|string|max:255',
            'special_dates_date.*' => 'nullable|date',
        ]);

        $validated['created_by'] = auth()->id();

        $validated = $this->handleSpecialDays($request, $validated);

        $project = Project::create($validated);

        if ($request->has('emails')) {
            $project->emails()->createMany($request->emails);
        }

        return $this->success($project->load('emails'), 'Project created successfully', 201);
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project): JsonResponse
    {
        return $this->success($project->load('emails'));
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'project_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'client_name' => 'nullable|string|max:255',
            'client_contact' => 'nullable|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'website_live_date' => 'nullable|date',
            'client_contacted_date' => 'nullable|date',
            'domain_name' => 'nullable|string|max:255',
            'domain_purchased_date' => 'nullable|date',
            'website_url' => 'nullable|string|max:255',
            'domain_expiry_date' => 'nullable|date',
            'domain_purchased_from' => 'nullable|string|max:255',
            'is_email_purchased' => 'nullable|boolean',
            'status' => 'nullable|in:' . implode(',', self::STATUSES),
            'project_manager_id' => 'nullable|exists:employees,id',
            'team_lead_id' => 'nullable|exists:employees,id',
            'emails' => 'nullable|array',
            'emails.*.email_name' => 'required|string|max:255',
            'emails.*.purchase_date' => 'nullable|date',
            'emails.*.expiry_date' => 'nullable|date',
            'special_dates_name.*' => 'nullable|string|max:255',
            'special_dates_date.*' => 'nullable|date',
        ]);

        $validated = $this->handleSpecialDays($request, $validated);

        $project->update($validated);

        if ($request->has('emails')) {
            $project->emails()->delete();
            $project->emails()->createMany($request->emails);
        }

        return $this->success($project->load('emails'), 'Project updated successfully');
    }

    private function handleSpecialDays(Request $request, array $data): array
    {
        $names = $request->special_dates_name;
        $dates = $request->special_dates_date;

        $specialDates = [];

        if ($names && is_array($names)) {
            foreach ($names as $key => $name) {
                if ($name) {
                    $specialDates[] = [
                        'name' => $name,
                        'date' => $dates[$key] ?? null,
                    ];
                }
            }
        }

        $data['special_dates'] = !empty($specialDates) ? $specialDates : null;

        return $data;
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): JsonResponse
    {
        $project->update(['deleted_by' => auth()->id()]);
        $project->delete();

        return $this->success(null, 'Project deleted successfully');
    }
}
