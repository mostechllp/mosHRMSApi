<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('organizations')->insert([
            [
                'id'                     => 1,
                'name'                   => 'Mostech Business Solutions',
                'phone'                  => '971581730112',
                'email'                  => 'info@mostech.ae',
                'logo'                   => null,
                'has_multiple_companies' => 0,
                'address'                => 'Dubai, UAE',
                'created_by'             => null,
                'deleted_by'             => null,
                'created_at'             => '2026-06-03 13:00:28',
                'updated_at'             => '2026-06-03 13:00:28',
                'deleted_at'             => null,
            ],
        ]);
    }
}
