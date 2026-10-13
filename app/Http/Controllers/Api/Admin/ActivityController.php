<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Activity;
use App\Models\Employee;
use App\Http\Controllers\Controller;
use App\Exports\ActivityExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;


class ActivityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $activities = Activity::with('assignedEmployee')
            ->when($request->related_record_type, function ($q) use ($request) {
                return $q->where('related_record_type', $request->related_record_type);
            })
            ->when($request->related_record_id, function ($q) use ($request) {
                return $q->where('related_record_id', $request->related_record_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($activities);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'activity_type' => 'nullable|string',
            'priority' => 'nullable|string',
            'related_record_type' => 'nullable|string',
            'related_record_id' => 'nullable|integer',
            'assigned_employee_id' => 'required|integer', // might not be explicitly 'exists:users,id' if users table doesn't exist, but let's assume it does or just integer
            'status' => 'nullable|string',
            'start_date_time' => 'required|date',
            'end_date_time' => 'nullable|date|after_or_equal:start_date_time',
            'reminder' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity = Activity::create($validator->validated());

        return response()->json(['message' => 'Activity created successfully', 'data' => $activity], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Activity $activity)
    {
        $activity->load('assignedEmployee');
        return response()->json($activity);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Activity $activity)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'activity_type' => 'nullable|string',
            'priority' => 'nullable|string',
            'related_record_type' => 'nullable|string',
            'related_record_id' => 'nullable|integer',
            'assigned_employee_id' => 'sometimes|required|integer',
            'status' => 'nullable|string',
            'start_date_time' => 'sometimes|required|date',
            'end_date_time' => 'nullable|date|after_or_equal:start_date_time',
            'reminder' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity->update($validator->validated());

        return response()->json(['message' => 'Activity updated successfully', 'data' => $activity]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        $activity->delete();

        return response()->json(['message' => 'Activity deleted successfully']);
    }

    /**
     * Export activities (supports format=xlsx, format=csv, format=pdf).
     */
    public function export(Request $request)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $activities = Activity::with('assignedEmployee')->orderBy('created_at', 'desc')->get();

        $rows = [];
        foreach ($activities as $activity) {
            $assignedEmployeeName = $activity->assignedEmployee
                ? trim($activity->assignedEmployee->first_name . ' ' . $activity->assignedEmployee->last_name)
                : '';

            $rows[] = [
                $activity->id,
                $activity->title ?? '',
                $activity->activity_type ?? '',
                $activity->priority ?? '',
                $activity->related_record_type ?? '',
                $activity->related_record_id ?? '',
                $assignedEmployeeName,
                $activity->status ?? '',
                $activity->start_date_time ?? '',
                $activity->end_date_time ?? '',
                $activity->reminder ?? '',
                $activity->description ?? '',
            ];
        }

        $format = strtolower($request->input('format', 'xlsx'));
        $filename = 'activities_' . now()->format('Y-m-d');

        if ($format === 'pdf') {
            $export = new ActivityExport($rows);
            $pdf = Pdf::loadView('reports.generic_pdf', [
                'data'     => $export->array(),
                'headings' => $export->headings(),
                'title'    => 'Activities Report',
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename . '.pdf');
        }

        if ($format === 'csv') {
            return Excel::download(new ActivityExport($rows), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new ActivityExport($rows), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    public function getEmployees(Request $request): JsonResponse
    {
        $query = Employee::select('id', 'user_id', 'employee_id', 'first_name', 'last_name')
            ->with([
                'user' => function ($q) {
                    $q->select('id', 'organization_id', 'department_id', 'designation_id', 'type')
                        ->with([
                            'organization:id,name',
                            'department:id,name',
                            'designation:id,name'
                        ]);
                }
            ])
            ->whereHas('user', function ($q) {
                $q->where('type', '!=', 'admin')
                    ->where('status', 'active');
            });

        if ($request->filled('search')) {
            $search = $request->query('search');

            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $employees = $query->get()->map(function ($employee) {
            $data = $employee->toArray();

            if (isset($data['user'])) {
                $user = $data['user'];
                $data['user_type'] = $user['type'] ?? null;
                $data['organization'] = $user['organization'] ?? null;
                $data['department'] = $user['department'] ?? null;
                $data['designation'] = $user['designation'] ?? null;
                unset($data['user']);
            }

            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $employees,
        ]);
    }
}
