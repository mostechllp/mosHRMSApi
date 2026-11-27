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
        Schema::create('accounts_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            // ── Basic information ─────────────────────────────────────────────
            $table->string('name', 255); // Name *
            $table->string('type', 50)->default('Goods'); // Goods | Service
            $table->string('usage_unit', 100)->nullable(); // pcs, box, kg, hrs, etc.

            // ── Sales information ─────────────────────────────────────────────
            $table->decimal('selling_price', 15, 2)->default(0.00); // Selling price *
            $table->string('selling_currency', 20)->default('INR');
            $table->string('sales_account', 150)->default('Sales'); // Sales account *
            $table->text('sales_description')->nullable(); // Description for invoices

            // ── Purchase information ──────────────────────────────────────────
            $table->decimal('cost_price', 15, 2)->default(0.00); // Cost price *
            $table->string('cost_currency', 20)->default('INR');
            $table->string('purchase_account', 150)->default('Cost of Goods Sold'); // Purchase account *
            $table->foreignId('preferred_vendor_id')->nullable()->constrained('accounts_vendors')->nullOnDelete();
            $table->string('preferred_vendor_name', 255)->nullable(); // fallback string vendor name
            $table->text('purchase_description')->nullable(); // Description for bills

            // ── Metadata & Status ─────────────────────────────────────────────
            $table->string('status', 20)->default('active'); // active | inactive
            $table->string('source', 50)->default('user'); // user | import

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
        Schema::dropIfExists('accounts_items');
    }
};
