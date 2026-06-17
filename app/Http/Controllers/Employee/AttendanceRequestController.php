<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttendanceRequest;
use Illuminate\Support\Facades\Auth;

class AttendanceRequestController extends Controller
{
    public function index()
    {
        $employeeId = Auth::guard('employee')->user()->id;
        $requests = AttendanceRequest::where('employee_id', $employeeId)->latest()->get();
        return view('employee.attendance_requests.index', compact('requests'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string|in:early_check_in,late_check_in,missed_punch_in,missed_punch_out',
            'request_date' => 'required|string', // Validated as string then parsed
            'request_time' => 'required|date_format:H:i',
            'reason' => 'required|string|max:1000',
        ]);

        $employeeId = Auth::guard('employee')->user()->id;
        
        try {
            // Attempt to parse d-m-Y, otherwise try Y-m-d
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $request->request_date)) {
                $formattedDate = \Carbon\Carbon::createFromFormat('d-m-Y', $request->request_date)->format('Y-m-d');
            } else {
                $formattedDate = \Carbon\Carbon::parse($request->request_date)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['request_date' => 'The date format is invalid. Use DD-MM-YYYY or YYYY-MM-DD.']);
        }

        AttendanceRequest::create([
            'employee_id' => $employeeId,
            'type' => $request->type,
            'request_date' => $formattedDate,
            'request_time' => $request->request_time,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Your request has been submitted successfully.');
    }
}
