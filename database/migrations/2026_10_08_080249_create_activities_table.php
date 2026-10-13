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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('activity_type')->nullable(); // Call, Meeting, Task
            $table->string('priority')->nullable(); // Low, Medium, High
            $table->string('related_record_type')->nullable(); // Lead, etc
            $table->unsignedBigInteger('related_record_id')->nullable(); // To store the related record ID, e.g., lead_id
            $table->unsignedBigInteger('assigned_employee_id')->nullable();
            $table->string('status')->nullable(); // Planned, Completed, etc.
            $table->dateTime('start_date_time')->nullable();
            $table->dateTime('end_date_time')->nullable();
            $table->string('reminder')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
