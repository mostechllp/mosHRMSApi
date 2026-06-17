<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('employees')->insert([
            [
                'id'                    => 1,
                'first_name'            => 'Super',
                'last_name'             => 'Admin',
                'avatar'                => null,
                'user_id'               => '1',
                'employee_id'           => 'MSC-HR-0001',
                'moh_license_number'    => null,
                'dob'                   => '1985-03-12',
                'joining_date'          => '2020-01-15',
                'gender'                => 'female',
                'marital_status'        => 'married',
                'nationality'           => 'Emirati',
                'special_days'          => null,
                'passport_full_name'    => null,
                'passport_number'       => null,
                'passport_issued_from'  => null,
                'passport_issued_date'  => null,
                'passport_expiry_date'  => null,
                'place_of_birth'        => null,
                'father_name'           => null,
                'mother_name'           => null,
                'address'               => 'Al Barsha, Dubai, UAE',
                'passport_1st_page'     => null,
                'passport_2nd_page'     => null,
                'passport_outer_page'   => null,
                'passport_id_page'      => null,
                'visa_type'             => null,
                'visa_number'           => null,
                'visa_issued_date'      => null,
                'visa_expiry_date'      => null,
                'visa_page'             => null,
                'labor_number'          => null,
                'labor_issued_date'     => null,
                'labor_expiry_date'     => null,
                'labor_card'            => null,
                'eid_number'            => null,
                'eid_issued_date'       => null,
                'eid_expiry_date'       => null,
                'eid_1st_page'          => null,
                'eid_2nd_page'          => null,
                'dependents'            => null,
                'educational_1st_page'  => null,
                'educational_2nd_page'  => null,
                'home_country_id_proof' => null,
                'additional_documents'  => null,
                'company_mobile_number' => '971551001001',
                'personal_number'       => '971551001002',
                'other_number'          => null,
                'home_country_number'   => null,
                'company_email'         => 'hr@mostech.ae',
                'personal_email'        => 'mostechllc@gmail.com',
                'is_skilled'            => 1,
                'experience_level'      => 'senior',
                'key_skills'            => 'HR Management | Recruitment | Payroll | Labour Law',
                'highest_education'     => 'MBA - Human Resource Management',
                'currency'              => 'AED',
                'payment_cycle'         => 'monthly',
                'created_by'            => 1,
                'deleted_by'            => null,
                'created_at'            => '2026-06-03 13:00:28',
                'updated_at'            => '2026-06-03 13:00:28',
                'deleted_at'            => null,
            ]
        ]);
    }
}
