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
            return Command::SUCCESS;
        }

        // Find recipients: Admins + Project Managers / Team Leads of affected projects
        $adminEmails = User::where('type', 'admin')
            ->orWhereHas('role', function ($q) {
                $q->whereIn('name', ['Super Admin', 'Admin', 'HR Manager']);
            })
            ->pluck('email')
            ->filter()
            ->unique()
            ->toArray();

        $recipientEmails = array_unique(array_filter($adminEmails));

        foreach ($recipientEmails as $email) {
            try {
                Mail::to($email)->send(new ProjectDomainExpiryMail('Admin', $domainsList, $emailsList, $daysThreshold));
                $this->info("Expiry notification sent to: {$email}");
            } catch (\Exception $e) {
                Log::error("Failed to send domain expiry notification to {$email}: " . $e->getMessage());
                $this->error("Failed to send to {$email}: " . $e->getMessage());
            }
        }

        $this->info("Domain and Email expiry notifications processed successfully.");
        return Command::SUCCESS;
    }
}
