<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Super Admin',     'description' => 'Full access to all modules and system settings'],
            ['name' => 'Sub Admin',           'description' => 'Administrative access to all system modules'],
            ['name' => 'HR Manager',      'description' => 'Manage leaves, payroll, documents & onboarding'],
            ['name' => 'Department Head', 'description' => 'Manage department staff, attendance & reports'],
            ['name' => 'Employee',        'description' => 'Standard staff access — self-service only'],
        ];

        $rows = [];
        foreach ($roles as $index => $r) {
            $rows[] = [
                'id'          => $index + 1,
                'name'        => $r['name'],
                'guard_name'  => 'api',
                'description' => $r['description'],
                'status'      => 'active',
                'created_at'  => '2026-06-03 13:00:28',
                'updated_at'  => '2026-06-03 13:00:28',
            ];
        }

        DB::table('roles')->insert($rows);
    }
}
