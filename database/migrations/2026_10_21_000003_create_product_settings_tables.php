<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Units (Unit, License, Project, User/Month, Month, Year, Hour, Day)
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        // Currencies (INR, USD, AED, GBP with symbol and default indicator)
        Schema::create('product_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10);
            $table->string('symbol', 10)->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tax Settings (GST 18%, GST 12%, GST 5%, Exempt)
        Schema::create('product_tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2)->default(0.00);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tax_rates');
        Schema::dropIfExists('product_currencies');
        Schema::dropIfExists('product_units');
    }
};
