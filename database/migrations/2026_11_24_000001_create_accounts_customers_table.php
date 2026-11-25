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
        Schema::create('accounts_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            // ── Basic Information ─────────────────────────────────────────────
            $table->string('customer_type', 50)->default('Business'); // Business | Individual
            $table->string('salutation', 20)->nullable(); // Mr., Mrs., Ms., Miss, Dr.
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('company_name', 255)->nullable();
            $table->string('display_name', 255); // Plain text input (Required)
            $table->string('email', 255)->nullable();
            $table->string('country', 100)->default('India'); // Country * dropdown in Basic info
            $table->string('work_phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();

            // ── Tab 1: Other Details ──────────────────────────────────────────
            $table->string('gstin_pan', 50)->nullable(); // Labelled "GSTIN / PAN"
            $table->string('pan', 50)->nullable(); // Compatibility alias
            $table->json('currencies')->nullable(); // Selected currency pills: e.g. ["INR", "USD"]
            $table->string('base_currency', 20)->default('INR'); // Base Currency dropdown
            $table->json('exchange_rates')->nullable(); // e.g. {"USD": 0.00, "EUR": 0.00, "GBP": 0.00, ...}
            $table->decimal('opening_balance', 15, 2)->default(0.00);
            $table->string('payment_terms', 100)->default('Due on receipt');
            $table->string('receivable_account', 100)->default('Accounts Receivable');
            $table->boolean('portal_access')->default(false);

            // ── Tab 2: Address Details ────────────────────────────────────────
            // Billing Address
            $table->string('billing_attention', 255)->nullable();
            $table->string('billing_country', 100)->nullable();
            $table->string('billing_street1', 255)->nullable();
            $table->string('billing_street2', 255)->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 100)->nullable();
            $table->string('billing_pin_code', 30)->nullable();
            $table->string('billing_phone', 50)->nullable();
            $table->string('billing_fax', 50)->nullable();

            // Shipping Address
            $table->string('shipping_attention', 255)->nullable();
            $table->string('shipping_country', 100)->nullable();
            $table->string('shipping_street1', 255)->nullable();
            $table->string('shipping_street2', 255)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 100)->nullable();
            $table->string('shipping_pin_code', 30)->nullable();
            $table->string('shipping_phone', 50)->nullable();
            $table->string('shipping_fax', 50)->nullable();

            // ── Tab 3: Contact Persons (JSON backup/quick access) ──────────────
            $table->json('contact_persons')->nullable();

            // ── Tab 4: Remarks (Internal notes) ───────────────────────────────
            $table->text('remarks')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts_customers');
    }
};
