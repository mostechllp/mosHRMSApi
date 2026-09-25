<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Models\ProjectEmail;
use App\Models\User;
use App\Mail\ProjectDomainExpiryMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

use App\Notifications\DomainExpiryNotification;
use Illuminate\Support\Facades\Notification;

class SendDomainExpiryNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects:notify-domain-expiries {--days=30 : Number of threshold days for expiration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send email notifications for expiring or expired project domains and project email accounts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysThreshold = (int) $this->option('days');
        $today = Carbon::today();
        $targetDate = $today->copy()->addDays($daysThreshold);

        // Expiring / Expired Domains
        $expiringProjects = Project::with(['projectManager.user', 'teamLead.user'])
            ->whereNotNull('domain_expiry_date')
            ->where('domain_expiry_date', '<=', $targetDate->toDateString())
            ->get();

        $domainsList = [];
        foreach ($expiringProjects as $project) {
            $expiry = Carbon::parse($project->domain_expiry_date);
            $daysRemaining = (int) $today->diffInDays($expiry, false);

            $domainsList[] = [
                'project_id'             => $project->id,
                'project_name'           => $project->project_name,
                'client_name'            => $project->client_name,
                'domain_name'            => $project->domain_name,
                'domain_expiry_date'     => $project->domain_expiry_date,
                'domain_purchased_from'  => $project->domain_purchased_from,
                'days_remaining'         => $daysRemaining,
                'status'                 => $daysRemaining < 0 ? 'expired' : 'expiring_soon',
                'project_manager_email'  => $project->projectManager?->company_email ?: $project->projectManager?->personal_email,
                'team_lead_email'        => $project->teamLead?->company_email ?: $project->teamLead?->personal_email,
            ];
        }

        // Expiring / Expired Project Emails
        $expiringEmails = ProjectEmail::with(['project.projectManager.user', 'project.teamLead.user'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $targetDate->toDateString())
            ->get();

        $emailsList = [];
        foreach ($expiringEmails as $emailModel) {
            $expiry = Carbon::parse($emailModel->expiry_date);
            $daysRemaining = (int) $today->diffInDays($expiry, false);
            $project = $emailModel->project;

            $emailsList[] = [
                'email_id'               => $emailModel->id,
                'email_name'             => $emailModel->email_name,
                'purchase_date'          => $emailModel->purchase_date,
                'expiry_date'            => $emailModel->expiry_date,
                'days_remaining'         => $daysRemaining,
                'status'                 => $daysRemaining < 0 ? 'expired' : 'expiring_soon',
                'project_id'             => $project?->id,
                'project_name'           => $project?->project_name,
                'project_manager_email'  => $project?->projectManager?->company_email ?: $project?->projectManager?->personal_email,
                'team_lead_email'        => $project?->teamLead?->company_email ?: $project?->teamLead?->personal_email,
            ];
        }

        if (empty($domainsList) && empty($emailsList)) {
            $this->info("No domains or project emails expiring within {$daysThreshold} days.");
            return self::SUCCESS;
        }

        // Target active HR users
        $hrUsers = User::where('type', 'hr')
            ->where('status', 'active')
            ->get();

        if ($hrUsers->isEmpty()) {
            $this->warn('No active HR users found to notify.');
            return self::SUCCESS;
        }

        // 1. Send Database & Email Notifications to HR users
        foreach ($domainsList as $domainItem) {
            $statusText = $domainItem['status'] === 'expired' ? 'expired' : 'expiring soon';
            $data = [
                'type' => 'domain_expiry',
                'title' => "Domain Expiry Alert: {$domainItem['domain_name']}",
                'name' => $domainItem['domain_name'],
                'project_name' => $domainItem['project_name'],
                'expiry_date' => $domainItem['domain_expiry_date'],
                'days_remaining' => $domainItem['days_remaining'],
                'status' => $domainItem['status'],
                'message' => "The domain '{$domainItem['domain_name']}' for project '{$domainItem['project_name']}' is {$statusText} (Expiry Date: {$domainItem['domain_expiry_date']}).",
            ];

            foreach ($hrUsers as $user) {
                try {
                    $user->notify(new DomainExpiryNotification($data));
                } catch (\Throwable $e) {
                    Log::error("Failed to notify HR user {$user->id} ({$user->email}): " . $e->getMessage());
                }
            }
        }

        foreach ($emailsList as $emailItem) {
            $statusText = $emailItem['status'] === 'expired' ? 'expired' : 'expiring soon';
            $data = [
                'type' => 'project_email_expiry',
                'title' => "Project Email Expiry Alert: {$emailItem['email_name']}",
                'name' => $emailItem['email_name'],
                'project_name' => $emailItem['project_name'],
                'expiry_date' => $emailItem['expiry_date'],
                'days_remaining' => $emailItem['days_remaining'],
                'status' => $emailItem['status'],
                'message' => "The project email '{$emailItem['email_name']}' for project '{$emailItem['project_name']}' is {$statusText} (Expiry Date: {$emailItem['expiry_date']}).",
            ];

            foreach ($hrUsers as $user) {
                try {
                    $user->notify(new DomainExpiryNotification($data));
                } catch (\Throwable $e) {
                    Log::error("Failed to notify HR user {$user->id} ({$user->email}): " . $e->getMessage());
                }
            }
        }

        $this->info("Domain and Email expiry notifications dispatched to HR users successfully.");
        return self::SUCCESS;
    }
}
