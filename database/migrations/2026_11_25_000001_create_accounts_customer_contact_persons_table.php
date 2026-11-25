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
        Schema::create('accounts_customer_contact_persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('accounts_customers')->cascadeOnDelete();

            // 6 Contact Person fields
            $table->string('salutation', 20)->nullable(); // Mr., Mrs., Ms., Miss, Dr.
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('work_phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts_customer_contact_persons');
    }
};
