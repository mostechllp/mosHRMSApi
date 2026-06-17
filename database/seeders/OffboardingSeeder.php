<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OffboardingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('offboardings')->insert([
            [
                'id'                  => 1,
                'employee_id'         => 2,
                'status'              => 'pending_checklist',
                'last_working_day'    => '2026-06-10',
                'separation_type'     => 'resignation',
                'notice_period_days'  => 30,
                'notice_start_date'   => '2026-06-24',
                'visa_sponsorship'    => 'Company sponsored',
                'nationality'         => 'British',
                'reason_for_leaving'  => 'XSACs',
                'created_at'          => '2026-06-04 08:49:57',
                'updated_at'          => '2026-06-04 09:28:58',
            ],
        ]);

        DB::table('offboarding_interviews')->insert([
            [
                'id'                        => 1,
                'offboarding_id'            => 1,
                'interviewer'               => 'Fatima Al Zaabi (HR)',
                'interview_date'            => '2026-06-17',
                'interview_mode'            => 'In person',
                'overall_satisfaction'      => 'Satisfied',
                'primary_reason'            => 'Better opportunity',
                'work_life_rating'          => null,
                'manager_relationship_rating' => null,
                'enjoyed_most'              => 'Collaborative team, flexible hours, and a strong learning culture.',
                'areas_for_improvement'     => 'Career growth paths and promotion timelines could be clearer.',
                'would_recommend'           => 0,
                'created_at'                => '2026-06-04 09:29:43',
                'updated_at'                => '2026-06-04 09:35:07',
            ],
        ]);
    }
}
