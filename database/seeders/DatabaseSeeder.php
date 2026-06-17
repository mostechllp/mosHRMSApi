<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters — respects foreign key dependencies.
     */
    public function run(): void
    {
        $this->call([
            // Core lookups (no FK deps)
            OrganizationSeeder::class,
            CompanySeeder::class,
            DepartmentSeeder::class,
            DesignationSeeder::class,
            RoleSeeder::class,

            // Users depend on roles, orgs, companies, departments, designations
            UserSeeder::class,

            // Employees depend on nothing except the user record by user_id string
            EmployeeSeeder::class,

            // Employee sub-tables
            // EmployeeBankSalarySeeder::class,

            // Leave
            // LeaveSeeder::class,

            // Modules & role permissions
            ModuleAndRolePermissionSeeder::class,

            // Working hours
            WorkingHoursSeeder::class,

            // Documents & folders
            // FolderDocumentSeeder::class,

            // Assets
            // AssetSeeder::class,

            // Offboarding (depends on employees)
            // OffboardingSeeder::class,
        ]);
    }
}
