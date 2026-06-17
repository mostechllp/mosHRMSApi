<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Company;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function employeeDetails()
    {
        $employees = Employee::with(['company', 'department', 'designation'])->get();
        return view('reports.employee_details', compact('employees'));
    }

    public function employeeNearestExpiry()
    {
        $threshold = Carbon::now()->addDays(30);
        $employees = Employee::where(function($query) use ($threshold) {
            $query->whereDate('passport_expiry_date', '<=', $threshold)
                  ->orWhereDate('visa_expiry_date', '<=', $threshold)
                  ->orWhereDate('labor_expiry_date', '<=', $threshold)
                  ->orWhereDate('eid_expiry_date', '<=', $threshold);
        })->get();
        
        return view('reports.employee_expiry', [
            'employees' => $employees,
            'title' => 'Employee Nearest Expiry Details',
            'subtitle' => 'Expiring within 30 days'
        ]);
    }

    public function employeeUpcomingRenewals()
    {
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);
        
        $employees = Employee::where(function($query) use ($start, $end) {
            $query->whereBetween('passport_expiry_date', [$start, $end])
                  ->orWhereBetween('visa_expiry_date', [$start, $end])
                  ->orWhereBetween('labor_expiry_date', [$start, $end])
                  ->orWhereBetween('eid_expiry_date', [$start, $end]);
        })->get();

        return view('reports.employee_expiry', [
            'employees' => $employees,
            'title' => 'Employee Upcoming Renewals',
            'subtitle' => 'Expiring within 31-90 days'
        ]);
    }

    public function companyNearestExpiry()
    {
        $threshold = Carbon::now()->addDays(30);
        $companies = Company::where(function($query) use ($threshold) {
            $query->whereDate('trade_license_expiry', '<=', $threshold)
                  ->orWhereDate('establishment_card_expiry', '<=', $threshold);
        })->get();

        return view('reports.company_expiry', [
            'companies' => $companies,
            'title' => 'Company Nearest Expiry Details',
            'subtitle' => 'Expiring within 30 days'
        ]);
    }

    public function companyUpcomingRenewals()
    {
        $start = Carbon::now()->addDays(31);
        $end = Carbon::now()->addDays(90);
        
        $companies = Company::where(function($query) use ($start, $end) {
            $query->whereBetween('trade_license_expiry', [$start, $end])
                  ->orWhereBetween('establishment_card_expiry', [$start, $end]);
        })->get();

        return view('reports.company_expiry', [
            'companies' => $companies,
            'title' => 'Company Upcoming Renewals',
            'subtitle' => 'Expiring within 31-90 days'
        ]);
    }

    public function attendanceReport(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->toDateString());

        $logs = AttendanceLog::with('user')
            ->whereBetween('log_date', [$startDate, $endDate])
            ->orderBy('log_date', 'desc')
            ->get();

        return view('reports.attendance', compact('logs', 'startDate', 'endDate'));
    }

    public function leaveRequestsReport()
    {
        $leaves = LeaveRequest::with(['employee', 'leaveType'])->orderBy('created_at', 'desc')->get();
        return view('reports.leaves', [
            'leaves' => $leaves,
            'title' => 'Employee Leave Request Reports'
        ]);
    }

    public function pendingLeavesReport()
    {
        $leaves = LeaveRequest::with(['employee', 'leaveType'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return view('reports.leaves', [
            'leaves' => $leaves,
            'title' => 'Employees Pending Leave Reports'
        ]);
    }
}
