<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opportunity Stages (New, Qualified, Proposal Sent, Negotiation, Won, Lost)
        Schema::create('opportunity_stages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('color', 30)->default('#6366F1');
            $table->decimal('default_probability', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        // Opportunity Lost Reasons (Price too high, Lost to competitor ...)
        Schema::create('opportunity_lost_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        // Opportunity Win Reasons (Best price, Better product fit ...)
        Schema::create('opportunity_win_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        // Opportunity Types (New Business, Upsell, Cross-sell, Renewal)
        Schema::create('opportunity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
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
        Schema::dropIfExists('opportunity_types');
        Schema::dropIfExists('opportunity_win_reasons');
        Schema::dropIfExists('opportunity_lost_reasons');
        Schema::dropIfExists('opportunity_stages');
    }
};
