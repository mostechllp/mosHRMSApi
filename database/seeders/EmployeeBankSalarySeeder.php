<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeBankSalarySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('employee_bank_details')->insert([
            [
                'id'             => 1,
                'employee_id'    => 1,
                'bank_country'   => 'UAE',
                'bank_name'      => 'First Abu Dhabi Bank',
                'account_number' => '0123456789',
                'iban_number'    => 'AE070331000123456789012',
                'swift_code'     => 'NBADAEAAXXX',
                'branch_name'    => 'Al Barsha',
                'ifsc_code'      => null,
                'created_at'     => '2026-06-03 13:00:28',
                'updated_at'     => '2026-06-03 13:00:28',
            ],
            [
                'id'             => 2,
                'employee_id'    => 2,
                'bank_country'   => 'UAE',
                'bank_name'      => 'Emirates NBD',
                'account_number' => '1029384756',
                'iban_number'    => 'AE070331001029384756001',
                'swift_code'     => 'EBILAEAD',
                'branch_name'    => 'JVC Branch',
                'ifsc_code'      => null,
                'created_at'     => '2026-06-03 13:00:28',
                'updated_at'     => '2026-06-03 13:00:28',
            ],
            [
                'id'             => 3,
                'employee_id'    => 3,
                'bank_country'   => 'UAE',
                'bank_name'      => 'Mashreq Bank',
                'account_number' => '5647382910',
                'iban_number'    => 'AE070331005647382910001',
                'swift_code'     => 'BOMLAEAD',
                'branch_name'    => 'Mirdif City Centre',
                'ifsc_code'      => null,
                'created_at'     => '2026-06-03 13:00:28',
                'updated_at'     => '2026-06-03 13:00:28',
            ],
        ]);

        DB::table('employee_salary_components')->insert([
            ['id' =>  1, 'employee_id' => 1, 'component_name' => 'Basic Salary',       'value' => 12000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' =>  2, 'employee_id' => 1, 'component_name' => 'Housing Allowance',  'value' =>  4000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' =>  3, 'employee_id' => 1, 'component_name' => 'Transport Allowance','value' =>  1000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' =>  4, 'employee_id' => 1, 'component_name' => 'Medical Allowance',  'value' =>   500.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' =>  5, 'employee_id' => 2, 'component_name' => 'Basic Salary',       'value' => 35000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' =>  6, 'employee_id' => 2, 'component_name' => 'Housing Allowance',  'value' => 12000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' =>  7, 'employee_id' => 2, 'component_name' => 'Transport Allowance','value' =>  2000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' =>  8, 'employee_id' => 2, 'component_name' => 'Medical Allowance',  'value' =>  1000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' =>  9, 'employee_id' => 3, 'component_name' => 'Basic Salary',       'value' => 32000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' => 10, 'employee_id' => 3, 'component_name' => 'Housing Allowance',  'value' => 11000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' => 11, 'employee_id' => 3, 'component_name' => 'Transport Allowance','value' =>  2000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' => 12, 'employee_id' => 3, 'component_name' => 'Medical Allowance',  'value' =>  1000.00, 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
        ]);
    }
}
