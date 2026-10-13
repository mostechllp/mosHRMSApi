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
        Schema::create('lead_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('follow_up_type', 50)->default('Call');
            $table->dateTime('follow_up_date');
            $table->enum('status', ['scheduled', 'completed', 'cancelled', 'missed'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->text('outcome')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_follow_ups');
    }
};
