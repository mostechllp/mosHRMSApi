<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeePreOnboardingChecklist extends Model
{
    use HasFactory;

    protected $table = 'employee_pre_onboarding_checklists';

    protected $fillable = [
        'employee_id',
        // HR Tasks
        'hr_emp_info_completed',
        'hr_id_proof_verified',
        'hr_academic_cert_verified',
        'hr_employment_ref_verified',
        'hr_all_docs_verified',
        'hr_offer_letter_generated',
        'hr_offer_letter_sent',
        'hr_offer_letter_accepted',
        // IT Tasks
        'it_company_email_created',
        'it_hrms_account_created',
        'it_system_access_created',
        'it_software_configured',
        // WhatsApp Groups
        'wa_personal_added',
        'wa_personal_added_date',
        'wa_personal_added_by',
        'wa_team_added',
        'wa_team_added_date',
        'wa_team_added_by',
        // Welcome Announcement
        'welcome_poster_published',
        'welcome_poster_published_date',
        'welcome_poster_published_by',
        // Google Meet Introduction
        'meet_date',
        'meet_time',
        'meet_link',
        'meet_calendar_invite_sent',
        'meet_completed',
    ];

    protected $casts = [
        'hr_emp_info_completed' => 'boolean',
        'hr_id_proof_verified' => 'boolean',
        'hr_academic_cert_verified' => 'boolean',
        'hr_employment_ref_verified' => 'boolean',
        'hr_all_docs_verified' => 'boolean',
        'hr_offer_letter_generated' => 'boolean',
        'hr_offer_letter_sent' => 'boolean',
        'hr_offer_letter_accepted' => 'boolean',
        'it_company_email_created' => 'boolean',
        'it_hrms_account_created' => 'boolean',
        'it_system_access_created' => 'boolean',
        'it_software_configured' => 'boolean',
        'wa_personal_added' => 'boolean',
        'wa_personal_added_date' => 'date',
        'wa_team_added' => 'boolean',
        'wa_team_added_date' => 'date',
        'welcome_poster_published' => 'boolean',
        'welcome_poster_published_date' => 'date',
        'meet_date' => 'date',
        'meet_calendar_invite_sent' => 'boolean',
        'meet_completed' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
