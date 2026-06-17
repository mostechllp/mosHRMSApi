<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('asset_types')->insert([
            ['id' => 1, 'name' => 'Tablet', 'description' => null, 'created_at' => '2026-06-04 11:51:41', 'updated_at' => '2026-06-04 11:51:41'],
            ['id' => 2, 'name' => 'Router', 'description' => null, 'created_at' => '2026-06-04 11:56:29', 'updated_at' => '2026-06-04 11:56:29'],
            ['id' => 3, 'name' => 'Webcam', 'description' => null, 'created_at' => '2026-06-04 12:15:27', 'updated_at' => '2026-06-04 12:35:29'],
        ]);
    }
}
