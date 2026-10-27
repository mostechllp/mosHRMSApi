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
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();

            // Basic Information
            $table->string('opportunity_id', 50)->unique()->nullable(); // e.g. OPP-0008
            $table->string('name', 255); // Opportunity Name
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('contact_person', 255)->nullable();
            $table->string('lead_source', 100)->nullable(); // Website, Referral, Cold Call, LinkedIn, etc.
            $table->json('products')->nullable(); // Multi-select tags: HRMS, ERP, CRM, Payroll, etc.
            $table->string('product', 100)->nullable(); // Primary product representation
            $table->text('description')->nullable();

            // Sales Information
            $table->decimal('estimated_value', 15, 2)->default(0.00);
            $table->string('currency', 10)->default('INR');
            $table->string('stage', 50)->default('New'); // New, Qualified, Proposal Sent, Negotiation, Won, Lost
            $table->decimal('probability', 5, 2)->default(20.00); // 0 to 100 %
            $table->date('expected_closing_date')->nullable();
            $table->foreignId('assigned_salesperson_id')->constrained('employees')->cascadeOnDelete();
            $table->string('sales_team', 100)->nullable();
            $table->string('priority', 50)->default('Medium'); // Low, Medium, High

            // Additional Information
            $table->string('competitor', 255)->nullable(); // e.g. Oracle, SAP
            $table->string('decision_maker', 255)->nullable(); // e.g. CTO, CFO
            $table->text('customer_requirements')->nullable();
            $table->string('next_action', 255)->nullable(); // e.g. Send proposal by Friday
            $table->text('notes')->nullable();

            // Audit metadata
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Opportunity Attachments
        Schema::create('opportunity_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Add opportunity_id to quotations table if not present
        if (Schema::hasTable('quotations') && !Schema::hasColumn('quotations', 'opportunity_id')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->foreignId('opportunity_id')->nullable()->after('lead_id')->constrained('opportunities')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('quotations') && Schema::hasColumn('quotations', 'opportunity_id')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropForeign(['opportunity_id']);
                $table->dropColumn('opportunity_id');
            });
        }

        Schema::dropIfExists('opportunity_attachments');
        Schema::dropIfExists('opportunities');
    }
};
