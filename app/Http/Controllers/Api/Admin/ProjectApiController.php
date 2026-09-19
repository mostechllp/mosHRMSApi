<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Models\ProjectEmail;
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

    /**
     * Get domain and project email expiry notifications/alerts.
     */
    public function getExpiryNotifications(Request $request): JsonResponse
    {
        $daysThreshold = (int) ($request->query('days', 30));
        $today = \Carbon\Carbon::today();

        // Fetch project domains
        $projects = Project::with(['projectManager', 'teamLead', 'department'])
            ->whereNotNull('domain_expiry_date')
            ->get();

        $domains = [];
        foreach ($projects as $project) {
            $expiry = \Carbon\Carbon::parse($project->domain_expiry_date);
            $daysRemaining = (int) $today->diffInDays($expiry, false);

            if ($daysRemaining <= $daysThreshold) {
                $status = $daysRemaining < 0 ? 'expired' : ($daysRemaining <= 7 ? 'critical' : 'expiring_soon');
                $domains[] = [
                    'project_id'             => $project->id,
                    'project_name'           => $project->project_name,
                    'client_name'            => $project->client_name,
                    'domain_name'            => $project->domain_name,
                    'domain_expiry_date'     => $project->domain_expiry_date,
                    'domain_purchased_from'  => $project->domain_purchased_from,
                    'domain_purchased_date'  => $project->domain_purchased_date,
                    'website_url'            => $project->website_url,
                    'is_email_purchased'     => $project->is_email_purchased,
                    'days_remaining'         => $daysRemaining,
                    'status'                 => $status,
                    'project_manager'        => $project->projectManager ? trim($project->projectManager->first_name . ' ' . $project->projectManager->last_name) : null,
                    'team_lead'              => $project->teamLead ? trim($project->teamLead->first_name . ' ' . $project->teamLead->last_name) : null,
                ];
            }
        }

        // Fetch project emails
        $projectEmails = ProjectEmail::with(['project.projectManager', 'project.teamLead'])
            ->whereNotNull('expiry_date')
            ->get();

        $emails = [];
        foreach ($projectEmails as $emailModel) {
            $expiry = \Carbon\Carbon::parse($emailModel->expiry_date);
            $daysRemaining = (int) $today->diffInDays($expiry, false);

            if ($daysRemaining <= $daysThreshold) {
                $status = $daysRemaining < 0 ? 'expired' : ($daysRemaining <= 7 ? 'critical' : 'expiring_soon');
                $project = $emailModel->project;
                $emails[] = [
                    'id'                     => $emailModel->id,
                    'email_name'             => $emailModel->email_name,
                    'purchase_date'          => $emailModel->purchase_date,
                    'expiry_date'            => $emailModel->expiry_date,
                    'days_remaining'         => $daysRemaining,
                    'status'                 => $status,
                    'project_id'             => $project?->id,
                    'project_name'           => $project?->project_name,
                    'client_name'            => $project?->client_name,
                    'project_manager'        => $project?->projectManager ? trim($project->projectManager->first_name . ' ' . $project->projectManager->last_name) : null,
                    'team_lead'              => $project?->teamLead ? trim($project->teamLead->first_name . ' ' . $project->teamLead->last_name) : null,
                ];
            }
        }

        // Sort by days_remaining ascending (expired/critical first)
        usort($domains, fn($a, $b) => $a['days_remaining'] <=> $b['days_remaining']);
        usort($emails, fn($a, $b) => $a['days_remaining'] <=> $b['days_remaining']);

        $expiredDomainsCount = collect($domains)->where('status', 'expired')->count();
        $expiringSoonDomainsCount = collect($domains)->whereIn('status', ['critical', 'expiring_soon'])->count();

        $expiredEmailsCount = collect($emails)->where('status', 'expired')->count();
        $expiringSoonEmailsCount = collect($emails)->whereIn('status', ['critical', 'expiring_soon'])->count();

        return $this->success([
            'days_threshold' => $daysThreshold,
            'summary' => [
                'total_domain_alerts' => count($domains),
                'expired_domains'     => $expiredDomainsCount,
                'expiring_domains'    => $expiringSoonDomainsCount,
                'total_email_alerts'  => count($emails),
                'expired_emails'      => $expiredEmailsCount,
                'expiring_emails'     => $expiringSoonEmailsCount,
                'total_alerts'        => count($domains) + count($emails),
            ],
            'domains' => $domains,
            'emails'  => $emails,
        ], 'Domain and project email expiry notifications retrieved successfully');
    }

    /**
     * Trigger sending domain and project email expiry notifications manually.
     */
    public function sendExpiryNotifications(Request $request): JsonResponse
    {
        $daysThreshold = (int) ($request->input('days', 30));
        $recipientEmail = $request->input('recipient_email');

        \Illuminate\Support\Facades\Artisan::call('projects:notify-domain-expiries', [
            '--days' => $daysThreshold,
        ]);

        $output = \Illuminate\Support\Facades\Artisan::output();

        if ($recipientEmail) {
            $notificationsData = $this->getExpiryNotifications($request)->getData(true);
            $domains = $notificationsData['data']['domains'] ?? [];
            $emails = $notificationsData['data']['emails'] ?? [];

            if (!empty($domains) || !empty($emails)) {
                \Illuminate\Support\Facades\Mail::to($recipientEmail)
                    ->send(new \App\Mail\ProjectDomainExpiryMail('User', $domains, $emails, $daysThreshold));
            }
        }

        return $this->success([
            'console_output' => trim($output),
        ], 'Expiry notification emails sent successfully');
    }
}

