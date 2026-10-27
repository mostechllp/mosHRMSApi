<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lead Statuses (New, Contacted, Qualified, Unqualified, Converted …)
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('color', 30)->default('#6366F1')->comment('Hex or named colour for the dot indicator');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        // Lead Sources (Website, Referral, Social Media …)
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        // Lead Priorities (Low, Medium, High)
        Schema::create('lead_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('color', 30)->default('#F59E0B')->comment('Hex colour for the dot indicator');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        // Lead Qualification Rules (Budget confirmed, Decision maker identified …)
        Schema::create('lead_qualification_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule', 255);
            $table->boolean('is_active')->default(true)->comment('Checked / enabled state shown in UI');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_qualification_rules');
        Schema::dropIfExists('lead_priorities');
        Schema::dropIfExists('lead_sources');
        Schema::dropIfExists('lead_statuses');
    }
};
