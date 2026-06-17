<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('companies')->insert([
            [
                'id'              => 1,
                'organization_id' => 1,
                'company_name'    => 'Mostech Business Solutions',
                'phone'           => '971581730112',
                'email'           => 'info@mostech.ae',
                'logo'            => null,
                'address'         => 'Dubai, UAE',
                'trade_license'   => 'mainland',
                'created_by'      => null,
                'deleted_by'      => null,
                'created_at'      => '2026-06-03 13:00:28',
                'updated_at'      => '2026-06-03 13:00:28',
                'deleted_at'      => null,
            ],
        ]);
    }
}
