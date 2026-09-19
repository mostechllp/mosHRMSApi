<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offboarding_handovers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('offboarding_id')
                ->constrained('offboardings')
                ->cascadeOnDelete();

            $table->boolean('task_and_projects')->default(false);
            $table->boolean('files_and_contents')->default(false);
            $table->boolean('reporting_manager_confirmation')->default(false);

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_handovers');
    }
};