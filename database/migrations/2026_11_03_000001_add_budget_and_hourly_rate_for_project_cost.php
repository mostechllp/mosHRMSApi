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
        // 1. Add budget to projects table if missing
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'budget')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->decimal('budget', 15, 2)->nullable()->after('status')->comment('Project budget in AED');
            });
        }

        // 2. Add hourly_rate to employees table if missing
        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'hourly_rate')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->decimal('hourly_rate', 10, 2)->nullable()->default(0.00)->after('payment_cycle')->comment('Employee default hourly cost in AED');
            });
        }

        // 3. Add hourly_rate override to employee_project pivot table if missing
        if (Schema::hasTable('employee_project') && !Schema::hasColumn('employee_project', 'hourly_rate')) {
            Schema::table('employee_project', function (Blueprint $table) {
                $table->decimal('hourly_rate', 10, 2)->nullable()->after('project_id')->comment('Project-specific hourly rate override in AED');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'budget')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('budget');
            });
        }

        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'hourly_rate')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('hourly_rate');
            });
        }

        if (Schema::hasTable('employee_project') && Schema::hasColumn('employee_project', 'hourly_rate')) {
            Schema::table('employee_project', function (Blueprint $table) {
                $table->dropColumn('hourly_rate');
            });
        }
    }
};

