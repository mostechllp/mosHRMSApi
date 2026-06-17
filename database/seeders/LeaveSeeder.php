<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        $leaveTypes = [
            'Sick Leave', 'Annual Leave', 'Casual Leave', 'Unpaid Leave',
            'Maternity Leave', 'Paternity Leave', 'Emergency Leave',
            'Study / Exam Leave', 'Bereavement Leave',
        ];

        $typeRows = [];
        foreach ($leaveTypes as $index => $name) {
            $typeRows[] = [
                'id'         => $index + 1,
                'name'       => $name,
                'status'     => 1,
                'created_by' => null,
                'deleted_by' => null,
                'created_at' => '2026-06-03 13:00:28',
                'updated_at' => '2026-06-03 13:00:28',
                'deleted_at' => null,
            ];
        }
        DB::table('leave_types')->insert($typeRows);

        // leave_allocations: 3 employees x 3 leave types
        $allocations = [
            [1, 1, 14.00], [1, 2, 22.00], [1, 3, 7.00],
            [2, 1, 14.00], [2, 2, 22.00], [2, 3, 7.00],
            [3, 1, 14.00], [3, 2, 22.00], [3, 3, 7.00],
        ];

        $allocationRows = [];
        foreach ($allocations as $index => [$empId, $leaveTypeId, $days]) {
            $allocationRows[] = [
                'id'             => $index + 1,
                'employee_id'    => $empId,
                'leave_type_id'  => $leaveTypeId,
                'year'           => 2026,
                'allocated_days' => $days,
                'created_at'     => '2026-06-03 13:00:28',
                'updated_at'     => '2026-06-03 13:00:28',
            ];
        }
        DB::table('leave_allocations')->insert($allocationRows);
    }
}
