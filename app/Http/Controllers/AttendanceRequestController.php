<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttendanceRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $requests = AttendanceRequest::with('employee')->latest()->get();
        return view('attendance_requests.index', compact('requests'));
    }

    /**
     * Update the status of the request.
     */
    public function updateStatus(Request $request, AttendanceRequest $attendanceRequest)
    {
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $attendanceRequest->update([
            'status' => $request->status,
        ]);

        return back()->with('success', 'Request status updated successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AttendanceRequest $attendanceRequest)
    {
        return view('attendance_requests.edit', compact('attendanceRequest'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AttendanceRequest $attendanceRequest)
    {
        $request->validate([
            'request_date' => 'required|string',
            'request_time' => 'required',
            'reason' => 'required|string',
        ]);

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

        $attendanceRequest->update([
            'request_date' => $formattedDate,
            'request_time' => $request->request_time,
            'reason' => $request->reason,
        ]);

        return redirect()->route('attendance_requests.index')->with('success', 'Request updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AttendanceRequest $attendanceRequest)
    {
        $attendanceRequest->delete();
        return back()->with('success', 'Request deleted successfully.');
    }
}
