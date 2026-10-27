<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tables:
     *   - quotation_currencies: id, name, created_at, updated_at, deleted_at
     *   - quotation_payment_terms: id, name, created_at, updated_at, deleted_at
     *   - quotation_tax_rates: id, name, rate, is_default, created_at, updated_at, deleted_at
     */
    public function up(): void
    {
        // Currencies - only id, name, created_at, updated_at, deleted_at
        Schema::create('quotation_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // Payment Terms - only id, name, created_at, updated_at, deleted_at
        Schema::create('quotation_payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        // Taxes - id, name, rate, is_default, created_at, updated_at, deleted_at
        Schema::create('quotation_tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2)->default(0.00);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_tax_rates');
        Schema::dropIfExists('quotation_payment_terms');
        Schema::dropIfExists('quotation_currencies');
    }
};
