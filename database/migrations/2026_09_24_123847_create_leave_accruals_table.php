<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_accruals', function (Blueprint $table) {

            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('leave_type_id')
                ->constrained('leave_types')
                ->cascadeOnDelete();

            $table->foreignId('leave_policy_id')
                ->constrained('leave_policies')
                ->cascadeOnDelete();

            // Example: 2026-09
            $table->string('period', 7);

            $table->date('accrual_date');

            $table->decimal('accrual_days', 5, 2);

            $table->enum('status', [
                'credited',
                'held',
                'released',
                'cancelled'
            ])->default('credited');

            $table->text('remarks')->nullable();

            $table->timestamps();

            /*
             * Prevent duplicate accrual for the same employee,
             * leave type and period.
             */
            $table->unique(
                ['employee_id', 'leave_type_id', 'period'],
                'unique_employee_leave_period'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_accruals');
    }
};