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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('lead_id', 50)->unique()->nullable();
            $table->string('lead_type', 50)->default('Company');
            $table->string('lead_name', 255);
            $table->string('company_name', 255)->nullable();
            $table->string('designation', 255)->nullable();
            $table->string('industry', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('lead_source', 100)->nullable();
            $table->string('lead_status', 100)->default('New');
            $table->string('priority', 50)->default('Medium');

            // Contact Details
            $table->string('email', 255)->nullable();
            $table->string('primary_phone', 50)->nullable();
            $table->string('alternate_phone', 50)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable()->default('India');

            // Sales Assignment
            $table->foreignId('assigned_salesperson_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('sales_team', 100)->nullable();
            $table->decimal('expected_value', 15, 2)->default(0.00);
            $table->date('expected_closing_date')->nullable();
            $table->json('interested_products')->nullable();
            $table->text('notes')->nullable();

            // Follow-up Summary
            $table->dateTime('next_follow_up_at')->nullable();
            $table->string('follow_up_type', 50)->nullable();
            $table->text('follow_up_notes')->nullable();

            // Audit metadata
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
