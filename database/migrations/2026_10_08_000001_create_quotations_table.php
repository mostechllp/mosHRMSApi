<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main quotations table
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();

            // Auto-generated reference number e.g. Q-2026-0001
            $table->string('quotation_number', 50)->unique()->nullable();

            // Customer & contact info
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('billing_address')->nullable();

            // Linked opportunity (Lead)
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();

            // Quotation details
            $table->date('quotation_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('currency', 10)->default('INR');
            $table->string('payment_terms', 100)->nullable();
            $table->string('delivery_timeline', 255)->nullable();

            // Financials (computed & stored for reporting)
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('discount_total', 15, 2)->default(0.00);
            $table->decimal('tax_total', 15, 2)->default(0.00);
            $table->decimal('additional_charges', 15, 2)->default(0.00);
            $table->decimal('grand_total', 15, 2)->default(0.00);

            // Extra info
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();

            // Status: draft, sent, accepted, rejected, expired, cancelled
            $table->string('status', 50)->default('draft');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Line items
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();

            $table->string('product_service', 255);
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0.00);

            // Discount: value or percent
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->string('discount_type', 10)->default('percent'); // 'percent' or 'fixed'

            $table->decimal('tax_percent', 5, 2)->default(0.00);
            $table->decimal('line_total', 15, 2)->default(0.00);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
