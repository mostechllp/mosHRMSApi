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
        if (!Schema::hasTable('onboarding_employees')) {
            Schema::create('onboarding_employees', function (Blueprint $table) {
                $table->id();
                $table->string('onboarding_id')->unique()->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('personal_email')->nullable();
                $table->string('company_email')->nullable();
                $table->string('personal_number')->nullable();
                $table->string('nationality')->nullable();
                $table->text('address')->nullable();
                $table->date('joining_date')->nullable();
                $table->date('dob')->nullable();
                $table->string('gender')->nullable();
                $table->string('marital_status')->nullable();
                $table->string('experience_level')->nullable();
                $table->text('key_skills')->nullable();
                $table->string('highest_education')->nullable();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('designation_id')->nullable()->constrained('designations')->nullOnDelete();
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->json('verification_data')->nullable();
                $table->json('salary_data')->nullable();
                $table->json('bank_data')->nullable();
                $table->json('checklist_data')->nullable();
                $table->string('resume_path')->nullable();
                $table->enum('status', ['onboarding', 'active'])->default('onboarding');
                $table->timestamp('activated_at')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_employees');
    }
};
