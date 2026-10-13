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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // A. Company Details
            $table->string('customer_id', 50)->unique()->nullable();
            $table->string('customer_type', 50)->default('Company');
            $table->string('company_name', 255);
            $table->string('industry', 255)->nullable();
            $table->string('company_registration_no', 100)->nullable();
            $table->string('tax_gst_no', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('customer_status', 50)->default('Active');
            $table->date('customer_since')->nullable();
            $table->foreignId('account_manager_id')->nullable()->constrained('employees')->nullOnDelete();

            // B. Address
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable()->default('India');
            $table->string('postal_code', 20)->nullable();

            // C. Primary Contact
            $table->string('contact_person_name', 255);
            $table->string('contact_designation', 255)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_alternate_phone', 50)->nullable();
            $table->string('preferred_communication', 50)->default('Email');

            // D. Business Details
            $table->string('customer_category', 100)->default('Customer');
            $table->string('customer_source', 100)->nullable();
            $table->string('payment_terms', 100)->default('Net 30');
            $table->decimal('credit_limit', 15, 2)->default(0.00);
            $table->text('notes')->nullable();

            // Audit
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
        Schema::dropIfExists('customers');
    }
};
