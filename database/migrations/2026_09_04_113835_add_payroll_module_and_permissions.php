<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Insert the payroll module ──────────────────────────────────
        // Guard against re-running (e.g. if seeder already added it)
        $exists = DB::table('modules')->where('slug', 'payroll')->exists();

        if (!$exists) {
            DB::table('modules')->insert([
                'name'       => 'Payroll',
                'slug'       => 'payroll',
                'route'      => '/payroll',
                'icon'       => 'bx-money',
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $moduleId = DB::table('modules')->where('slug', 'payroll')->value('id');

        // ── 2. Role → permissions matrix ─────────────────────────────────
        // [role_id, can_read, can_edit, can_delete]
        // Role IDs match the existing seeder:
        //   1 = Super Admin, 2 = Sub Admin, 3 = HR Manager,
        //   4 = Department Head, 5 = Employee
        $rolePermissions = [
            [1, 1, 1, 1], // Super Admin  — full access
            [2, 1, 1, 1], // Sub Admin    — full access
            [3, 1, 1, 0], // HR Manager   — read + edit, no delete
            [4, 1, 0, 0], // Dept Head    — read only
            [5, 0, 0, 0], // Employee     — no access
        ];

        foreach ($rolePermissions as [$roleId, $read, $edit, $delete]) {
            // Skip if the role doesn't exist
            if (!DB::table('roles')->where('id', $roleId)->exists()) {
                continue;
            }

            // Skip if a row already exists for this role + module
            $alreadyExists = DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('module_id', $moduleId)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

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

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('slug', 'payroll')->value('id');

        if ($moduleId) {
            DB::table('role_permissions')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('id', $moduleId)->delete();
        }
    }
};
