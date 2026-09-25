<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('leave_type_id')
                ->constrained('leave_types')
                ->cascadeOnDelete();

            // Example: 18 days per year
            $table->decimal('annual_allocation', 5, 2)->default(0);

            // Accrual settings
            $table->boolean('enable_accrual')->default(false);

            $table->enum('accrual_type', [
                'monthly',
                'quarterly',
                'half_yearly',
                'yearly',
                'daily'
            ])->nullable();

            $table->decimal('accrual_days', 5, 2)->nullable();

            // Probation settings
            $table->boolean('apply_during_probation')->default(false);

            $table->enum('probation_action', [
                'hold',
                'accrue',
                'accrue_and_restrict_usage'
            ])->nullable();

            $table->boolean('release_after_probation')->default(false);

            // Carry forward
            $table->boolean('enable_carry_forward')->default(false);

            $table->boolean('unlimited_carry_forward')->default(false);

            $table->decimal('maximum_carry_forward', 5, 2)
                ->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();

            // One policy per leave type
            $table->unique('leave_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
    }
};