<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Meeting Management API Controller
 *
 * Handles scheduling and management of meetings based on the
 * Schedule Meeting UI form:
 *
 *  - title          : Meeting Title (required)
 *  - date           : Meeting date (required)
 *  - start_time     : Start time string e.g. "10:00" (required)
 *  - end_time       : End time string e.g. "11:00" (optional)
 *  - host_user_id   : User ID of the host (defaults to the authenticated user)
 *  - participant_ids: Array of user IDs (multiple participants)
 *  - client_name    : Client Name (optional, free text)
 *  - client_email   : Client Email (optional)
 *  - related_to     : Free-text string field – stored as plain varchar, NOT an enum
 *  - description    : Agenda / context / notes
 *  - meeting_link   : e.g. https://meet.google.com/...
 *  - status         : Scheduled | Ongoing | Completed | Cancelled
 *
 * All endpoints are prefixed with /api/admin/crm/meetings/
 */
class MeetingApiController extends ApiController
{
    // ──────────────────────────────────────────────────────────────────────────
    //  INDEX – List all meetings (with filtering & pagination)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/meetings
     */
    public function index(Request $request): JsonResponse
    {
        $query = Meeting::with('host:id,username,email')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('host_user_id'), fn($q) => $q->where('host_user_id', $request->host_user_id))
            ->when($request->filled('date'), fn($q) => $q->whereDate('date', $request->date))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('date', '<=', $request->date_to))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('related_to', 'like', "%{$search}%");
                });
            })
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc');

        $perPage = (int) ($request->per_page ?? 15);
        $meetings = $query->paginate($perPage)->through(function ($meeting) {
            return $this->formatMeeting($meeting);
        });

        return $this->success($meetings, 'Meetings retrieved successfully');
    }

    public function employeeMeetings(Request $request): JsonResponse
    {
        $query = Meeting::with(['host:id,username,email', 'host.employee:id,user_id,avatar,first_name, last_name'])
            ->where('host_user_id', auth()->id())
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc');

        $perPage = (int) ($request->per_page ?? 15);
        $meetings = $query->paginate($perPage)->through(function ($meeting) {
            return $this->formatMeeting($meeting);
        });

        return $this->success($meetings, 'Meetings retrieved successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  STORE – Schedule a new meeting
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/admin/crm/meetings
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|string|max:10',
            'end_time' => 'nullable|string|max:10',
            'host_user_id' => 'nullable|integer|exists:users,id',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'integer|exists:users,id',
            'client_name' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'related_to' => 'nullable|string|max:255',   // free-text – NOT enum
            'description' => 'nullable|string',
            'meeting_link' => 'nullable|url|max:500',
            'status' => 'nullable|string|in:Scheduled,Ongoing,Completed,Cancelled',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $data = $validator->validated();

        // Default host to the authenticated user if not provided
        $data['host_user_id'] = $data['host_user_id'] ?? auth()->id();
        $data['status'] = $data['status'] ?? 'Scheduled';

        $meeting = Meeting::create($data);
        $meeting->load([
            'host:id,username,email',
            'host.employee:id,user_id,avatar, first_name, last_name',
        ]);

        return $this->success(
            $this->formatMeeting($meeting),
            'Meeting scheduled successfully',
            201
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  SHOW – Get a single meeting
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/meetings/{meeting}
     */
    public function show(Meeting $meeting): JsonResponse
    {
        $meeting->load([
            'host:id,username,email',
            'host.employee:id,user_id,avatar',
        ]);

        return $this->success(
            $this->formatMeeting($meeting),
            'Meeting retrieved successfully'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  UPDATE – Update an existing meeting
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * PUT /api/admin/crm/meetings/{meeting}
     */
    public function update(Request $request, Meeting $meeting): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date_format:Y-m-d',
            'start_time' => 'sometimes|required|string|max:10',
            'end_time' => 'nullable|string|max:10',
            'host_user_id' => 'nullable|integer|exists:users,id',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'integer|exists:users,id',
            'client_name' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'related_to' => 'nullable|string|max:255',   // free-text – NOT enum
            'description' => 'nullable|string',
            'meeting_link' => 'nullable|url|max:500',
            'status' => 'nullable|string|in:Scheduled,Ongoing,Completed,Cancelled',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $meeting->update($validator->validated());
        $meeting->load([
            'host:id,username,email',
            'host.employee:id,user_id,avatar, first_name, last_name'
        ]);

        return $this->success(
            $this->formatMeeting($meeting->fresh()),
            'Meeting updated successfully'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PATCH STATUS – Quick status update
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * PATCH /api/admin/crm/meetings/{meeting}/status
     */
    public function updateStatus(Request $request, Meeting $meeting): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:Scheduled,Ongoing,Completed,Cancelled',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $meeting->update(['status' => $request->status]);

        return $this->success(
            ['id' => $meeting->id, 'status' => $meeting->status],
            'Meeting status updated successfully'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  DESTROY – Soft-delete a meeting
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * DELETE /api/admin/crm/meetings/{meeting}
     */
    public function destroy(Meeting $meeting): JsonResponse
    {
        $meeting->delete();

        return $this->success(null, 'Meeting deleted successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  FORM OPTIONS – Data needed to render the Schedule Meeting form
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/meetings/form-options
     *
     * Returns users list for Host selector & Participants multi-select,
     * plus status options and the authenticated user's details (for Host default).
     */
    public function formOptions(Request $request): JsonResponse
    {
        // All active users for host / participant selectors
        $users = User::select('id', 'username', 'email', 'type')
            ->with('employee:id,user_id,avatar, first_name, last_name')
            ->where('status', 'active')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'type' => $user->type,
                    'avatar' => $user->employee?->avatar,
                    'employee_name' => $user->host->employee->first_name.' '.$user->host->employee->last_name ?? null,
                ];
            });

        // Current authenticated user (for Host "Defaults to you" behaviour)
        $currentUser = null;
        if (auth()->check()) {
            $u = auth()->user();
            $currentUser = [
                'id' => $u->id,
                'username' => $u->username,
                'email' => $u->email,
            ];
        }

        return $this->success([
            'users' => $users,
            'current_user' => $currentUser,
            'statuses' => ['Scheduled', 'Ongoing', 'Completed', 'Cancelled'],
            // related_to is a free-text field – no predefined options
        ], 'Meeting form options retrieved successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PARTICIPANTS SEARCH – Search users for participant multi-select
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/meetings/participants/search
     *
     * Search active users by name or email for the participants selector.
     */
    public function searchParticipants(Request $request): JsonResponse
    {
        $query = User::select('id', 'username', 'email')
            ->where('status', 'active');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->limit(30)->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ];
        });

        return $this->success($users, 'Participants retrieved successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Format a Meeting model for API output,
     * resolving participant user objects from participant_ids.
     */
    private function formatMeeting(Meeting $meeting): array
    {
        $data = $meeting->toArray();

        // Resolve participant user details
        $data['participants'] = $meeting->participants;

        // Host details
        if ($meeting->relationLoaded('host') && $meeting->host) {
            $data['host'] = [
                'id' => $meeting->host->id,
                'username' => $meeting->host->username,
                'email' => $meeting->host->email,
                'avatar' => $meeting->host->employee->avatar ?? null,
                'employee_name' => $meeting->host->employee->first_name.' '.$meeting->host->employee->last_name ?? null,
            ];
        }

        return $data;
    }
}
