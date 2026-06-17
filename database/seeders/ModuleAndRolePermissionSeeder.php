<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleAndRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Modules
        $modules = [
            ['name' => 'Dashboard',          'slug' => 'dashboard',  'route' => '/dashboard',  'icon' => 'bx-grid-alt'],
            ['name' => 'Onboarding',         'slug' => 'onboarding', 'route' => '/onboarding', 'icon' => 'bx-user-plus'],
            ['name' => 'Employees',          'slug' => 'employees',  'route' => '/employees',  'icon' => 'bx-group'],
            ['name' => 'Projects',           'slug' => 'projects',   'route' => '/projects',   'icon' => 'bx-briefcase'],
            ['name' => 'Attendance',         'slug' => 'attendance', 'route' => '/attendance', 'icon' => 'bx-calendar-check'],
            ['name' => 'Leave Management',   'slug' => 'leave',      'route' => '/leave',      'icon' => 'bx-calendar-x'],
            ['name' => 'Documents',          'slug' => 'documents',  'route' => '/documents',  'icon' => 'bx-file'],
            ['name' => 'Reports',            'slug' => 'reports',    'route' => '/reports',    'icon' => 'bx-bar-chart'],
            ['name' => 'Settings',           'slug' => 'settings',   'route' => '/settings',   'icon' => 'bx-cog'],
            ['name' => 'Roles & Permissions','slug' => 'roles',      'route' => '/roles',      'icon' => 'bx-shield'],
        ];

        $moduleRows = [];
        foreach ($modules as $index => $m) {
            $moduleRows[] = [
                'id'         => $index + 1,
                'name'       => $m['name'],
                'slug'       => $m['slug'],
                'route'      => $m['route'],
                'icon'       => $m['icon'],
                'status'     => 'active',
                'created_at' => '2026-06-03 13:00:28',
                'updated_at' => '2026-06-03 13:00:28',
            ];
        }
        DB::table('modules')->insert($moduleRows);

       // role_permissions matrix [role_id, module_id, can_read, can_edit, can_delete]
$permissions = [

    // Super Admin (1) - Full Access
    [1,1,1,1,1],[1,2,1,1,1],[1,3,1,1,1],[1,4,1,1,1],[1,5,1,1,1],
    [1,6,1,1,1],[1,7,1,1,1],[1,8,1,1,1],[1,9,1,1,1],[1,10,1,1,1],

    // Sub Admin (2) - Full Access except Roles & Permissions
    [2,1,1,1,1],[2,2,1,1,1],[2,3,1,1,1],[2,4,1,1,1],[2,5,1,1,1],
    [2,6,1,1,1],[2,7,1,1,1],[2,8,1,1,1],[2,9,1,1,1],[2,10,0,0,0],

    // HR Manager (3)
    [3,1,1,0,0], // Dashboard
    [3,2,1,1,1], // Onboarding
    [3,3,1,1,1], // Employees
    [3,4,1,0,0], // Projects
    [3,5,1,1,1], // Attendance
    [3,6,1,1,1], // Leave
    [3,7,1,1,1], // Documents
    [3,8,1,1,0], // Reports
    [3,9,0,0,0], // Settings
    [3,10,0,0,0], // Roles

    // Department Head (4)
    [4,1,1,0,0], // Dashboard
    [4,2,0,0,0], // Onboarding
    [4,3,1,1,0], // Employees
    [4,4,1,1,0], // Projects
    [4,5,1,1,0], // Attendance
    [4,6,1,1,0], // Leave
    [4,7,1,0,0], // Documents
    [4,8,1,1,0], // Reports
    [4,9,0,0,0], // Settings
    [4,10,0,0,0], // Roles

    // Employee (5)
    [5,1,1,0,0], // Dashboard
    [5,2,0,0,0], // Onboarding
    [5,3,1,0,0], // Employees (view own profile)
    [5,4,1,0,0], // Projects (assigned projects)
    [5,5,1,1,0], // Attendance
    [5,6,1,1,0], // Leave
    [5,7,1,0,0], // Documents
    [5,8,1,0,0], // Reports (own reports)
    [5,9,0,0,0], // Settings
    [5,10,0,0,0], // Roles
];

        $permRows = [];
        foreach ($permissions as $index => [$roleId, $moduleId, $read, $edit, $delete]) {
            $permRows[] = [
                'id'         => $index + 1,
                'role_id'    => $roleId,
                'module_id'  => $moduleId,
                'can_read'   => $read,
                'can_edit'   => $edit,
                'can_delete' => $delete,
                'created_at' => '2026-06-03 13:00:28',
                'updated_at' => '2026-06-03 13:00:28',
            ];
        }
        DB::table('role_permissions')->insert($permRows);
    }
}
