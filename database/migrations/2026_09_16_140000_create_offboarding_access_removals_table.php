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
        Schema::create('offboarding_access_removals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('offboarding_id')
                ->constrained('offboardings')
                ->cascadeOnDelete();

            $table->boolean('hrms_access_revoke')->default(false);
            $table->boolean('deactivate_company_email')->default(false);

            $table->text('other_access')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offboarding_access_removals');
    }
};
