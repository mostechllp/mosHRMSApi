<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the crm_permissions table that stores per-role access
     * for each granular CRM action (e.g. "view_all_customers", "create_leads").
     *
     * Uniqueness is enforced on (role_id, permission_key) so each role
     * can have exactly one row per action.
     */
    public function up(): void
    {
        Schema::create('crm_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permission_key', 100);   // e.g. "view_all_customers"
            $table->boolean('is_allowed')->default(false);
            $table->timestamps();
            $table->unique(['role_id', 'permission_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_permissions');
    }
};
