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
        Schema::table('offboarding_settlements', function (Blueprint $table) {
            if (!Schema::hasColumn('offboarding_settlements', 'deductions')) {
                $table->json('deductions')->nullable()->after('remarks');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offboarding_settlements', function (Blueprint $table) {
            if (Schema::hasColumn('offboarding_settlements', 'deductions')) {
                $table->dropColumn('deductions');
            }
        });
    }
};
