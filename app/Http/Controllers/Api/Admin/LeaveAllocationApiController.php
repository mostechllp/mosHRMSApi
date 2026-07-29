<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\LeaveAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\LeaveRequest;
use Illuminate\Support\Facades\DB;

class LeaveAllocationApiController extends ApiController
{
    /**
     * Display a listing of employee leave balances.
     */
    // public function index(): JsonResponse
    // {
    //     $employees = Employee::with(['user.designation', 'user.department', 'user.company'])->get();
    //     $leaveTypes = LeaveType::where('status', true)->get();

    //     return $this->success([
    //         'employees' => $employees,
    //         'leave_types' => $leaveTypes
    //     ]);
    // }

    public function index(): JsonResponse
    {
        $employees = Employee::with('user')
            ->whereHas('user', function ($query) {
                $query->where('type', '!=', 'admin');
            })
            ->get();

        $leaveTypes = LeaveType::where('status', true)->get();

        $usedLeaves = LeaveRequest::whereIn('employee_id', $employees->pluck('id'))
            ->where('status', 'approved')
            ->select('employee_id', 'leave_type_id', DB::raw('SUM(duration_days) as used_days'))
            ->groupBy('employee_id', 'leave_type_id')
            ->get()
            ->groupBy('employee_id');

        $allocatedLeaves = LeaveAllocation::whereIn('employee_id', $employees->pluck('id'))
            ->select('employee_id', 'leave_type_id', DB::raw('SUM(allocated_days) as allocated_days'))
            ->groupBy('employee_id', 'leave_type_id')
            ->get()
            ->groupBy('employee_id');

        $result = $employees->map(function ($employee) use ($leaveTypes, $usedLeaves, $allocatedLeaves) {
            $employeeUsed = $usedLeaves->get($employee->id, collect());
            $employeeAllocated = $allocatedLeaves->get($employee->id, collect());

            return [
                'employee_name' => $employee->first_name . ' ' . $employee->last_name ?? null,
                'leave_types' => $leaveTypes->map(function ($leaveType) use ($employeeUsed, $employeeAllocated) {
                    $used = (float) (optional(
                        $employeeUsed->firstWhere('leave_type_id', $leaveType->id)
                    )->used_days ?? 0);

                    $allocated = (float) (optional(
                        $employeeAllocated->firstWhere('leave_type_id', $leaveType->id)
                    )->allocated_days ?? 0);

                    return [
                        'leave_type' => $leaveType->name,
                        'allocated'  => $allocated,
                        'used'       => $used,
                        'balance'    => $allocated - $used,
                    ];
                })->values(),
            ];
        });

        return $this->success($result);
    }

    /**
     * Get allocations for a specific employee.
     */
    public function show(Employee $employee): JsonResponse
    {
        $leaveTypes = LeaveType::where('status', true)->get();
        $allocations = LeaveAllocation::where('employee_id', $employee->id)
            ->where('year', date('Y'))
            ->get()
            ->keyBy('leave_type_id');

        return $this->success([
            'employee' => $employee,
            'leave_types' => $leaveTypes,
            'allocations' => $allocations
        ]);
    }

    /**
     * Update/Store leave allocations for an employee.
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'allocations' => 'required|array',
            'allocations.*' => 'required|integer|min:0',
        ]);

        $results = [];
        foreach ($request->allocations as $leaveTypeId => $days) {
            $results[] = LeaveAllocation::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'leave_type_id' => $leaveTypeId,
                    'year' => date('Y'),
                ],
                [
                    'allocated_days' => $days,
                ]
            );
        }

        return $this->success($results, 'Leave allocations updated successfully.');
    }
}
