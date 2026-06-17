<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\LeaveAllocation;
use Illuminate\Http\Request;

class LeaveAllocationController extends Controller
{
    /**
     * Display a listing of employee leave balances.
     */
    public function index()
    {
        $employees = Employee::with(['designation', 'department', 'company'])->get();
        $leaveTypes = LeaveType::where('status', true)->get();

        return view('leaves.allocations.index', compact('employees', 'leaveTypes'));
    }

    /**
     * Show the form for editing lead allocations for a specific employee.
     */
    public function edit(Employee $employee)
    {
        $leaveTypes = LeaveType::where('status', true)->get();
        $allocations = LeaveAllocation::where('employee_id', $employee->id)
            ->where('year', date('Y'))
            ->get()
            ->keyBy('leave_type_id');

        return view('leaves.allocations.edit', compact('employee', 'leaveTypes', 'allocations'));
    }

    /**
     * Update/Store leave allocations for an employee.
     */
    public function update(Request $request, Employee $employee)
    {
        $request->validate([
            'allocations' => 'required|array',
            'allocations.*' => 'required|integer|min:0',
        ]);

        foreach ($request->allocations as $leaveTypeId => $days) {
            LeaveAllocation::updateOrCreate(
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

        return redirect()->route('leave-allocations.index')->with('success', 'Leave allocations updated successfully.');
    }
}
