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
        Schema::table('accounts_customers', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts_customers', 'source')) {
                $table->string('source', 50)->default('user')->after('remarks'); // 'user' or 'import'
            }
            if (!Schema::hasColumn('accounts_customers', 'status')) {
                $table->string('status', 20)->default('active')->after('source'); // 'active' or 'inactive'
            }
            if (!Schema::hasColumn('accounts_customers', 'unused_credits')) {
                $table->decimal('unused_credits', 15, 2)->default(0.00)->after('opening_balance');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts_customers', function (Blueprint $table) {
            if (Schema::hasColumn('accounts_customers', 'source')) {
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('accounts_customers', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('accounts_customers', 'unused_credits')) {
                $table->dropColumn('unused_credits');
            }
        });
    }
};
