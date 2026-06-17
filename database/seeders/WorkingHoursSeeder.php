<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkingHoursSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('working_hours')->insert([
            ['id' => 1, 'day' => 'Monday',    'is_enabled' => 1, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' => 2, 'day' => 'Tuesday',   'is_enabled' => 1, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' => 3, 'day' => 'Wednesday', 'is_enabled' => 1, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' => 4, 'day' => 'Thursday',  'is_enabled' => 1, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' => 5, 'day' => 'Friday',    'is_enabled' => 1, 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' => 6, 'day' => 'Saturday',  'is_enabled' => 1, 'start_time' => '09:00:00', 'end_time' => '13:00:00', 'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
            ['id' => 7, 'day' => 'Sunday',    'is_enabled' => 0, 'start_time' => null,        'end_time' => null,        'created_at' => '2026-06-03 13:00:28', 'updated_at' => '2026-06-03 13:00:28'],
        ]);
    }
}
