<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offboarding_leave_verifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('offboarding_id')
                ->constrained('offboardings')
                ->cascadeOnDelete();

            $table->boolean('leave_history_verified')->default(false);
            $table->boolean('process_for_encashment')->default(false);

            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offboarding_leave_verifications');
    }
};
