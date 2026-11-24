<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Activity Types (Call, Email, Meeting, Task, Site Visit)
        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        // Activity Reminder Options (None, 15 min, 1 hour, 1 day)
        Schema::create('activity_reminder_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('minutes')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Activity Priorities (Low, Medium, High)
        Schema::create('activity_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 30)->default('#10B981');
            $table->timestamps();
            $table->softDeletes();
        });

        // Activity General Settings (e.g., default_duration_minutes)
        Schema::create('activity_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_settings');
        Schema::dropIfExists('activity_priorities');
        Schema::dropIfExists('activity_reminder_options');
        Schema::dropIfExists('activity_types');
    }
};
