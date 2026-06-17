<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'id'                => 1,
                'role_id'           => 2,
                'username'          => 'hr@mostech.ae',
                'password'          => Hash::make('MoS@2026#'),
                'email'             => 'hr@mostech.ae',
                'email_verified_at' => null,
                'organization_id'   => null,
                'company_id'        => null,
                'department_id'     => null,
                'designation_id'    => null,
                'type'              => 'admin',
                'status'            => 'active',
                'created_by'        => null,
                'deleted_by'        => null,
                'remember_token'    => null,
                'created_at'        => '2026-06-03 13:00:28',
                'updated_at'        => '2026-06-03 13:00:28',
                'deleted_at'        => null,
            ]
        ]);
    }
}
