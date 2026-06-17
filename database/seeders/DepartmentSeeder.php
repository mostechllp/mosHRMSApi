<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'Web Development',
            'SEO',
            'Graphic Designing',
            'Project Management',
            'HR',
            'Sales & Marketing',
            'Content Development'
        ];

        $rows = [];
        foreach ($departments as $index => $name) {
            $rows[] = [
                'id'         => $index + 1,
                'name'       => $name,
                'created_by' => null,
                'deleted_by' => null,
                'created_at' => '2026-06-03 13:00:28',
                'updated_at' => '2026-06-03 13:00:28',
                'deleted_at' => null,
            ];
        }

        DB::table('departments')->insert($rows);
    }
}
