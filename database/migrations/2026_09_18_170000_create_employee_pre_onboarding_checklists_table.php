<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_pre_onboarding_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');

            // HR Tasks
            $table->boolean('hr_emp_info_completed')->default(false);
            $table->boolean('hr_id_proof_verified')->default(false);
            $table->boolean('hr_academic_cert_verified')->default(false);
            $table->boolean('hr_employment_ref_verified')->default(false);
            $table->boolean('hr_all_docs_verified')->default(false);
            $table->boolean('hr_offer_letter_generated')->default(false);
            $table->boolean('hr_offer_letter_sent')->default(false);
            $table->boolean('hr_offer_letter_accepted')->default(false);

            // IT Tasks
            $table->boolean('it_company_email_created')->default(false);
            $table->boolean('it_hrms_account_created')->default(false);
            $table->boolean('it_system_access_created')->default(false);
            $table->boolean('it_software_configured')->default(false);

            // Section 1 - WhatsApp Groups
            $table->boolean('wa_personal_added')->default(false);
            $table->date('wa_personal_added_date')->nullable();
            $table->string('wa_personal_added_by')->nullable();
            $table->boolean('wa_team_added')->default(false);
            $table->date('wa_team_added_date')->nullable();
            $table->string('wa_team_added_by')->nullable();

            // Section 2 - Welcome Announcement
            $table->boolean('welcome_poster_published')->default(false);
            $table->date('welcome_poster_published_date')->nullable();
            $table->string('welcome_poster_published_by')->nullable();

            // Section 3 - Google Meet Introduction
            $table->date('meet_date')->nullable();
            $table->string('meet_time')->nullable();
            $table->string('meet_link')->nullable();
            $table->boolean('meet_calendar_invite_sent')->default(false);
            $table->boolean('meet_completed')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_pre_onboarding_checklists');
    }
};
