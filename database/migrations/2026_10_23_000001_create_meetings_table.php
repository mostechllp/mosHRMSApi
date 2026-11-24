<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * meetings table supports the Schedule Meeting UI with:
     *  - host_user_id        : defaults to the currently authenticated user
     *  - participant_ids     : JSON array of user IDs
     *  - related_to          : plain string text field (NOT enum / dropdown)
     */
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->string('start_time', 10)->nullable();          // e.g. "10:00"
            $table->string('end_time', 10)->nullable();            // e.g. "11:00"
            $table->unsignedBigInteger('host_user_id')->nullable(); // FK -> users.id
            $table->json('participant_ids')->nullable();            // [1, 2, 3, ...]
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('related_to')->nullable();              // free-text, NOT enum
            $table->text('description')->nullable();
            $table->string('meeting_link')->nullable();
            $table->string('status')->default('Scheduled');        // Scheduled, Ongoing, Completed, Cancelled
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('host_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
