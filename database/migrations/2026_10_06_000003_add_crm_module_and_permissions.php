<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Insert CRM module if it doesn't exist
        $exists = DB::table('modules')->where('slug', 'crm')->exists();

        if (!$exists) {
            DB::table('modules')->insert([
                'name'       => 'CRM & Leads',
                'slug'       => 'crm',
                'route'      => '/crm/leads',
                'icon'       => 'bx-user-check',
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $moduleId = DB::table('modules')->where('slug', 'crm')->value('id');

        if ($moduleId) {
            // [role_id, can_read, can_edit, can_delete]
            $rolePermissions = [
                [1, 1, 1, 1], // Super Admin
                [2, 1, 1, 1], // Sub Admin
                [3, 1, 1, 0], // HR Manager
                [4, 1, 1, 0], // Dept Head / Manager
                [5, 1, 1, 0], // Employee / Sales Representative
            ];

            foreach ($rolePermissions as [$roleId, $read, $edit, $delete]) {
                if (!DB::table('roles')->where('id', $roleId)->exists()) {
                    continue;
                }

                $alreadyExists = DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('module_id', $moduleId)
                    ->exists();

                if (!$alreadyExists) {
                    DB::table('role_permissions')->insert([
                        'role_id'    => $roleId,
                        'module_id'  => $moduleId,
                        'can_read'   => $read,
                        'can_edit'   => $edit,
                        'can_delete' => $delete,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $moduleId = DB::table('modules')->where('slug', 'crm')->value('id');

        if ($moduleId) {
            DB::table('role_permissions')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('id', $moduleId)->delete();
        }
    }
};
