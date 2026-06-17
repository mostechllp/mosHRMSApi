<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DesignationSeeder extends Seeder
{
    public function run(): void
    {
        $designations = [
            ['name' => 'Chief Executive Officer (CEO)',      'default_punch_access' => 1],
            ['name' => 'Chief Technology Officer (CTO)',    'default_punch_access' => 1],
            ['name' => 'Chief Operating Officer (COO)',     'default_punch_access' => 1],
            ['name' => 'Director of Engineering',           'default_punch_access' => 1],
            ['name' => 'Engineering Manager',               'default_punch_access' => 1],
            ['name' => 'Project Manager',                   'default_punch_access' => 1],
            ['name' => 'Product Manager',                   'default_punch_access' => 1],
            ['name' => 'Team Lead',                         'default_punch_access' => 1],

            ['name' => 'Senior Software Engineer',          'default_punch_access' => 0],
            ['name' => 'Software Engineer',                 'default_punch_access' => 0],
            ['name' => 'Junior Software Engineer',          'default_punch_access' => 0],

            ['name' => 'Senior Frontend Developer',         'default_punch_access' => 0],
            ['name' => 'Frontend Developer',                'default_punch_access' => 0],

            ['name' => 'Senior Backend Developer',          'default_punch_access' => 0],
            ['name' => 'Backend Developer',                 'default_punch_access' => 0],

            ['name' => 'Full Stack Developer',              'default_punch_access' => 0],
            ['name' => 'Mobile App Developer',              'default_punch_access' => 0],

            ['name' => 'UI/UX Designer',                    'default_punch_access' => 0],
            ['name' => 'Graphic Designer',                  'default_punch_access' => 0],

            ['name' => 'QA Lead',                           'default_punch_access' => 0],
            ['name' => 'QA Engineer',                       'default_punch_access' => 0],
            ['name' => 'Software Tester',                   'default_punch_access' => 0],

            ['name' => 'DevOps Engineer',                   'default_punch_access' => 0],
            ['name' => 'Cloud Engineer',                    'default_punch_access' => 0],
            ['name' => 'System Administrator',              'default_punch_access' => 0],

            ['name' => 'Business Analyst',                  'default_punch_access' => 0],
            ['name' => 'Technical Support Engineer',        'default_punch_access' => 0],

            ['name' => 'Digital Marketing Executive',       'default_punch_access' => 0],
            ['name' => 'SEO Specialist',                    'default_punch_access' => 0],
            ['name' => 'Content Creator',                   'default_punch_access' => 0],

            ['name' => 'HR Manager',                        'default_punch_access' => 0],
            ['name' => 'HR Executive',                      'default_punch_access' => 0],

            ['name' => 'Finance Manager',                   'default_punch_access' => 0],
            ['name' => 'Accountant',                        'default_punch_access' => 0],

            ['name' => 'Administrative Officer',            'default_punch_access' => 0],
        ];

        $rows = [];

        foreach ($designations as $index => $d) {
            $rows[] = [
                'id'                   => $index + 1,
                'name'                 => $d['name'],
                'default_punch_access' => $d['default_punch_access'],
                'created_by'           => 1,
                'deleted_by'           => null,
                'created_at'           => now(),
                'updated_at'           => now(),
                'deleted_at'           => null,
            ];
        }

        DB::table('designations')->insert($rows);
    }
}