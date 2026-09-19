<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\OffboardingHandover;
use App\Models\Offboarding;
use App\Models\OffboardingChecklist;
use App\Models\EmployeeAsset;
use App\Models\OffboardingInterview;
use App\Models\OffboardingSettlement;
use App\Models\OffboardingLetter;
use App\Models\OffboardingLeaveVerification;
use App\Models\OffboardingAccessRemoval;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\AttendanceLog;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Carbon\Carbon;

#[OA\Tag(
    name: 'Offboarding',
    description: 'Endpoints for managing the employee offboarding lifecycle'
)]
class OffboardingApiController extends ApiController
{
    // -------------------------------------------------------------------------
    // INDEX
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding',
        operationId: 'listOffboardings',
        summary: 'List all offboarding records',
        description: 'Returns a paginated list of offboarding records. Admins and HR Managers see all records; other roles see only employees they manage.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        required: false,
        description: 'Page number for pagination',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding records retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    description: 'Laravel paginated result containing offboarding records'
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized access')]
    public function index(Request $request): JsonResponse
    {
        $query = Offboarding::with([
            'employee',
            'reportingManager',
            'checklists',
            'assets'
        ]);

        $user = $request->user();

        if ($user->type !== 'admin' && $user->role?->name !== 'HR Manager') {

            if ($user->employee) {
                $query->whereHas('employee', function ($q) use ($user) {
                    $q->where('reporting_manager_id', $user->employee->id);
                });
            } else {
                return $this->error('Unauthorized access', 403);
            }
        }

        $offboardings = $query->paginate(15);

        return $this->success($offboardings);
    }

    // -------------------------------------------------------------------------
    // STATS
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding/stats',
        operationId: 'getOffboardingStats',
        summary: 'Get offboarding dashboard card statistics',
        description: 'Returns aggregate counts for the offboarding dashboard cards: total, pending initiation, in progress, completed, and per-step pending counts (asset return, final settlement, visa cancellation, exit interview, letters & documents).',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding statistics fetched successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Offboarding statistics fetched successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'total_offboarding', type: 'integer', example: 24),
                        new OA\Property(property: 'pending_initiation', type: 'integer', example: 3),
                        new OA\Property(property: 'in_progress', type: 'integer', example: 15),
                        new OA\Property(property: 'completed_offboarding', type: 'integer', example: 6),
                        new OA\Property(property: 'pending_asset_return', type: 'integer', example: 9),
                        new OA\Property(property: 'pending_final_settlement', type: 'integer', example: 11),
                        new OA\Property(property: 'pending_visa_cancellation', type: 'integer', example: 5),
                        new OA\Property(property: 'pending_exit_interview', type: 'integer', example: 7),
                        new OA\Property(property: 'pending_letters_documents', type: 'integer', example: 13),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized access')]
    public function getStats(Request $request): JsonResponse
    {
        $query = Offboarding::with(['checklists', 'assets', 'interview', 'settlement', 'letters']);

        $user = $request->user();

        if ($user->type !== 'admin' && $user->role?->name !== 'HR Manager') {

            if ($user->employee) {
                $query->whereHas('employee', function ($q) use ($user) {
                    $q->where('reporting_manager_id', $user->employee->id);
                });
            } else {
                return $this->error('Unauthorized access', 403);
            }
        }

        $offboardings = $query->get();
        $activeOffboardings = $offboardings->where('status', '!=', 'completed');

        return $this->success([
            'total_offboarding' => $offboardings->count(),
            'pending_initiation' => $offboardings->where('status', 'draft')->count(),
            'in_progress' => $offboardings->whereNotIn('status', ['draft', 'completed'])->count(),
            'completed_offboarding' => $offboardings->where('status', 'completed')->count(),
            'pending_asset_return' => $activeOffboardings->filter(fn($o) => !$this->isAssetsReturned($o))->count(),
            'pending_final_settlement' => $activeOffboardings->filter(fn($o) => !$this->isSettlementCompleted($o))->count(),
            'pending_visa_cancellation' => $activeOffboardings->filter(fn($o) => !$this->isVisaCompleted($o))->count(),
            'pending_exit_interview' => $activeOffboardings->filter(fn($o) => !$this->isInterviewCompleted($o))->count(),
            'pending_letters_documents' => $activeOffboardings->filter(fn($o) => !$this->isLettersGenerated($o))->count(),
        ], 'Offboarding statistics fetched successfully.');
    }

    // -------------------------------------------------------------------------
    // INITIATE
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/initiate',
        operationId: 'initiateOffboarding',
        summary: 'Initiate or save a draft offboarding process',
        description: 'Creates or updates an offboarding record for an employee. Pass `is_draft: true` to save as draft, or `false` to formally initiate.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['employee_id', 'reporting_manager_id'],
            properties: [
                new OA\Property(
                    property: 'employee_id',
                    type: 'integer',
                    example: 2
                ),
                new OA\Property(
                    property: 'reporting_manager_id',
                    type: 'integer',
                    example: 5
                ),
                new OA\Property(
                    property: 'exit_initiation_date',
                    type: 'string',
                    format: 'date',
                    nullable: true,
                    example: '2026-05-01'
                ),
                new OA\Property(
                    property: 'last_working_day',
                    type: 'string',
                    format: 'date',
                    nullable: true,
                    example: '2026-06-10'
                ),
                new OA\Property(
                    property: 'separation_type',
                    type: 'string',
                    nullable: true,
                    example: 'resignation'
                ),
                new OA\Property(
                    property: 'resignation_date',
                    type: 'string',
                    format: 'date',
                    nullable: true,
                    example: '2026-05-10'
                ),
                new OA\Property(
                    property: 'notice_period_days',
                    type: 'integer',
                    nullable: true,
                    example: 30
                ),
                new OA\Property(
                    property: 'notice_start_date',
                    type: 'string',
                    format: 'date',
                    nullable: true,
                    example: '2026-05-10'
                ),
                new OA\Property(
                    property: 'visa_sponsorship',
                    type: 'string',
                    nullable: true,
                    example: 'Company sponsored'
                ),
                new OA\Property(
                    property: 'nationality',
                    type: 'string',
                    nullable: true,
                    example: 'British'
                ),
                new OA\Property(
                    property: 'acceptance_status',
                    type: 'string',
                    nullable: true,
                    example: 'pending'
                ),
                new OA\Property(
                    property: 'reason_for_leaving',
                    type: 'string',
                    nullable: true,
                    example: 'Better opportunity abroad'
                ),
                new OA\Property(
                    property: 'is_draft',
                    type: 'boolean',
                    nullable: true,
                    example: false
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding initiated or draft saved',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'success',
                    type: 'boolean',
                    example: true
                ),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Offboarding process initiated successfully.'
                ),
                new OA\Property(
                    property: 'data',
                    type: 'object'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Not authorized to initiate offboarding for this employee'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function initiate(Request $request): JsonResponse
    {
        /*
        |--------------------------------------------------------------------------
        | Validate Request
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'reporting_manager_id' => 'required|exists:employees,id',
            'exit_initiation_date' => 'nullable|date',
            'last_working_day' => 'nullable|date',
            'separation_type' => 'nullable|string',
            'resignation_date' => 'nullable|date',
            'notice_period_days' => 'nullable|integer|min:0',
            'notice_start_date' => 'nullable|date',
            'resignation_acceptance_status' => 'nullable|string',
            'reason_for_leaving' => 'nullable|string',
            'resignation_reason' => 'nullable|string',
            'is_draft' => 'nullable|boolean',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Get Employee
        |--------------------------------------------------------------------------
        */

        $employee = Employee::findOrFail(
            $validated['employee_id']
        );

        /*
        |--------------------------------------------------------------------------
        | Authorization Check
        |--------------------------------------------------------------------------
        */

        if (!$this->isAuthorized($request->user(), $employee)) {
            return $this->error(
                'You are not authorized to initiate offboarding for this employee.',
                403
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Determine Draft / Initiated Status
        |--------------------------------------------------------------------------
        */

        $isDraft = $validated['is_draft'] ?? false;

        /*
        |--------------------------------------------------------------------------
        | Create or Update Offboarding
        |--------------------------------------------------------------------------
        */

        $offboarding = Offboarding::updateOrCreate(
            [
                'employee_id' => $employee->id,
            ],
            [
                'status' => $isDraft
                    ? 'draft'
                    : 'pending_handover',

                'reporting_manager_id' =>
                    $validated['reporting_manager_id'],
                'exit_initiation_date' =>
                    $validated['exit_initiation_date'] ?? null,
                'last_working_day' =>
                    $validated['last_working_day'] ?? null,
                'separation_type' =>
                    $validated['separation_type'] ?? null,
                'resignation_date' =>
                    $validated['resignation_date'] ?? null,
                'notice_period_days' =>
                    $validated['notice_period_days'] ?? null,
                'notice_start_date' =>
                    $validated['notice_start_date'] ?? null,
                'acceptance_status' =>
                    $validated['resignation_acceptance_status'] ?? null,
                'reason_for_leaving' =>
                    $validated['reason_for_leaving'] ?? null,
                'resignation_reason' =>
                    $validated['resignation_reason'] ?? null,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return $this->success(
            $offboarding,
            $isDraft
            ? 'Offboarding draft saved successfully.'
            : 'Offboarding process initiated successfully.'
        );
    }

    #[OA\Get(
        path: '/api/admin/offboarding/{id}/handover',
        operationId: 'getHandover',
        summary: 'Get handover details for offboarding',
        description: 'Returns the handover record (task and projects, files and contents, reporting manager confirmation, notes) for the given offboarding ID.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Handover details retrieved successfully'
    )]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function getHandover(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with(['handover', 'employee'])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('You are not authorized to view this offboarding record.', 403);
        }

        return $this->success(
            $offboarding->handover,
            'Handover details retrieved successfully.'
        );
    }

    #[OA\Post(
        path: '/api/admin/offboarding/save-handover',
        operationId: 'saveHandover',
        summary: 'Save or update handover details',
        description: 'Creates or updates handover details for an offboarding process.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['offboarding_id'],
            properties: [
                new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                new OA\Property(property: 'task_and_projects', type: 'boolean', example: true),
                new OA\Property(property: 'files_and_contents', type: 'boolean', example: true),
                new OA\Property(property: 'reporting_manager_confirmation', type: 'boolean', example: true),
                new OA\Property(property: 'notes', type: 'string', example: 'All projects handed over to team lead.'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Handover details saved successfully'
    )]
    public function saveHandover(Request $request, $id = null): JsonResponse
    {
        $offboardingId = $id ?? $request->input('offboarding_id');

        $validated = $request->validate([
            'offboarding_id' => $id ? 'nullable|exists:offboardings,id' : 'required|exists:offboardings,id',
            'task_and_projects' => 'nullable|boolean',
            'files_and_contents' => 'nullable|boolean',
            'reporting_manager_confirmation' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $targetOffboardingId = $offboardingId;

        $offboarding = Offboarding::find($targetOffboardingId);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('You are not authorized to modify this offboarding record.', 403);
        }

        $handover = OffboardingHandover::updateOrCreate(
            [
                'offboarding_id' => $targetOffboardingId,
            ],
            [
                'task_and_projects' => $validated['task_and_projects'] ?? false,
                'files_and_contents' => $validated['files_and_contents'] ?? false,
                'reporting_manager_confirmation' => $validated['reporting_manager_confirmation'] ?? false,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        if ($offboarding->status === 'pending_handover') {
            $offboarding->update([
                'status' => 'pending_leave_check',
            ]);
        }

        return $this->success(
            $handover,
            'Handover details saved successfully.'
        );
    }

    #[OA\Get(
        path: '/api/admin/offboarding/{id}/leave-verification',
        operationId: 'getLeaveVerification',
        summary: 'Get leave verification details for offboarding',
        description: 'Returns the leave verification record (leave history verified, process for encashment, remarks) and employee leave allocations for the given offboarding ID.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Leave verification retrieved successfully'
    )]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function getLeaveVerification(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with(['leaveVerification', 'employee'])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('You are not authorized to view this offboarding record.', 403);
        }

        $leaveAllocations = LeaveAllocation::where('employee_id', $offboarding->employee_id)
            ->with('leaveType')
            ->get();

        return $this->success([
            'leave_verification' => $offboarding->leaveVerification,
            'leave_allocations' => $leaveAllocations,
        ], 'Leave verification details retrieved successfully.');
    }

    #[OA\Post(
        path: '/api/admin/offboarding/save-leave-verification',
        operationId: 'saveLeaveVerification',
        summary: 'Save leave verification & encashment details',
        description: 'Creates or updates leave verification and encashment details for an offboarding process.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['offboarding_id'],
            properties: [
                new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                new OA\Property(property: 'leave_history_verified', type: 'boolean', example: true),
                new OA\Property(property: 'process_for_encashment', type: 'boolean', example: true),
                new OA\Property(property: 'remarks', type: 'string', example: 'Leave balance verified successfully.'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Leave verification saved successfully'
    )]
    public function saveLeaveVerification(Request $request, $id = null): JsonResponse
    {
        $offboardingId = $id ?? $request->input('offboarding_id');

        $validated = $request->validate([
            'offboarding_id' => $id ? 'nullable|exists:offboardings,id' : 'required|exists:offboardings,id',
            'leave_history_verified' => 'nullable|boolean',
            'process_for_encashment' => 'nullable|boolean',
            'remarks' => 'nullable|string',
        ]);

        $targetOffboardingId = $offboardingId;

        $offboarding = Offboarding::find($targetOffboardingId);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('You are not authorized to modify this offboarding record.', 403);
        }

        $leaveVerification = OffboardingLeaveVerification::updateOrCreate(
            [
                'offboarding_id' => $targetOffboardingId,
            ],
            [
                'leave_history_verified' => $validated['leave_history_verified'] ?? false,
                'process_for_encashment' => $validated['process_for_encashment'] ?? false,
                'remarks' => $validated['remarks'] ?? null,
            ]
        );

        if ($offboarding->status === 'pending_leave_check') {
            $offboarding->update([
                'status' => 'pending_access',
            ]);
        }

        return $this->success(
            $leaveVerification,
            'Leave verification details saved successfully.'
        );
    }

    #[OA\Get(
        path: '/api/admin/offboarding/{id}/access-removal',
        operationId: 'getAccessRemoval',
        summary: 'Get access removal details for offboarding',
        description: 'Returns the access removal record (hrms_access_revoke, deactivate_company_email, other_access, notes) for the given offboarding ID.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Access removal details retrieved successfully'
    )]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function getAccessRemoval(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with(['accessRemoval', 'employee'])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('You are not authorized to view this offboarding record.', 403);
        }

        return $this->success(
            $offboarding->accessRemoval,
            'Access removal details retrieved successfully.'
        );
    }

    #[OA\Post(
        path: '/api/admin/offboarding/save-access-removal',
        operationId: 'saveAccessRemoval',
        summary: 'Save access removal details',
        description: 'Creates or updates access removal details for an offboarding process.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['offboarding_id'],
            properties: [
                new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                new OA\Property(property: 'hrms_access_revoke', type: 'boolean', example: true),
                new OA\Property(property: 'deactivate_company_email', type: 'boolean', example: true),
                new OA\Property(property: 'other_access', type: 'string', example: 'GitHub, Slack, AWS access revoked.'),
                new OA\Property(property: 'notes', type: 'string', example: 'All permissions revoked on last working day.'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Access removal details saved successfully'
    )]
    public function saveAccessRemoval(Request $request, $id = null): JsonResponse
    {
        $offboardingId = $id ?? $request->input('offboarding_id');

        $validated = $request->validate([
            'offboarding_id' => $id ? 'nullable|exists:offboardings,id' : 'required|exists:offboardings,id',
            'hrms_access_revoke' => 'nullable|boolean',
            'deactivate_company_email' => 'nullable|boolean',
            'other_access' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $targetOffboardingId = $offboardingId;

        $offboarding = Offboarding::find($targetOffboardingId);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('You are not authorized to modify this offboarding record.', 403);
        }

        $accessRemoval = OffboardingAccessRemoval::updateOrCreate(
            [
                'offboarding_id' => $targetOffboardingId,
            ],
            [
                'hrms_access_revoke' => $validated['hrms_access_revoke'] ?? false,
                'deactivate_company_email' => $validated['deactivate_company_email'] ?? false,
                'other_access' => $validated['other_access'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        if ($offboarding->status === 'pending_access') {
            $offboarding->update([
                'status' => 'pending_settlement',
            ]);
        }

        return $this->success(
            $accessRemoval,
            'Access removal details saved successfully.'
        );
    }

    public function reportingManagers(): JsonResponse
    {
        $employees = Employee::whereHas('user', function ($query) {
            $query->whereIn('type', ['manager', 'hr'])->where('status', 'active');
        })
            ->with(['user'])
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'user_id' => $employee->user_id,
                    'employee_id' => $employee->employee_id,
                    'full_name' => trim(
                        $employee->first_name . ' ' . $employee->last_name
                    ),
                    'user_type' => $employee->user?->type,

                    'department' => $employee->user?->department?->name,
                    'designation' => $employee->user?->designation?->name,
                ];
            });

        return $this->success($employees);
    }

    public function getAllEmployees(): JsonResponse
    {
        $offboardingEmployeeIds = Offboarding::pluck('employee_id');

        $employees = Employee::whereNotIn('id', $offboardingEmployeeIds)
            ->whereHas('user', function ($query) {
                $query->where('type', '!=', 'admin');
            })
            ->with(['user.department', 'user.designation'])
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'user_id' => $employee->user_id,
                    'employee_id' => $employee->employee_id,
                    'full_name' => trim(
                        $employee->first_name . ' ' . $employee->last_name
                    ),
                    'user_type' => $employee->user?->type,
                    'joining_date' => $employee->joining_date,
                    'department' => $employee->user?->department?->name,
                    'designation' => $employee->user?->designation?->name,
                    'email' => $employee->personal_email,
                ];
            });

        return $this->success($employees);
    }
    // -------------------------------------------------------------------------
    // SHOW
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding/{id}',
        operationId: 'showOffboarding',
        summary: 'Get full details of an offboarding record',
        description: 'Retrieves a single offboarding record with all related data: employee, checklists, assets, interview, settlement and letters.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding details retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Offboarding details retrieved successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function show(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'employee',
            'employee.user.department',
            'employee.user.designation',
            'handover',
            'leaveVerification',
            'accessRemoval',
            'checklists',
            'assets',
            'interview',
            'settlement',
            'letters',
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to view this offboarding record.',
                403
            );
        }

        return $this->success(
            $offboarding,
            'Offboarding details retrieved successfully.'
        );
    }

    // -------------------------------------------------------------------------
    // GET VISA STATUS
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding/{id}/visa-status',
        operationId: 'getVisaStatus',
        summary: 'Get visa cancellation details and checklist',
        description: 'Returns the employee\'s visa details along with all visa_cancellation checklist tasks and their completion progress.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Visa cancellation details fetched successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Visa cancellation details fetched successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(
                            property: 'employee',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 2),
                                new OA\Property(property: 'name', type: 'string', example: 'Dr. Vipul Paul Thomas'),
                                new OA\Property(property: 'employee_code', type: 'string', example: 'MSC-DOC-0002'),
                            ]
                        ),
                        new OA\Property(
                            property: 'visa_details',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'visa_number', type: 'string', example: '98765432111'),
                                new OA\Property(property: 'visa_expiry_date', type: 'string', format: 'date', example: '2026-06-30'),
                                new OA\Property(property: 'eid_number', type: 'string', example: '768-7679277893-88'),
                                new OA\Property(property: 'eid_expiry_date', type: 'string', format: 'date', example: '2026-07-02'),
                                new OA\Property(property: 'labor_number', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'visa_type', type: 'string', example: 'company_visa'),
                            ]
                        ),
                        new OA\Property(
                            property: 'tasks',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'task_name', type: 'string', example: 'Submit visa cancellation to GDRFA/ICP'),
                                    new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                    new OA\Property(property: 'responsible_role', type: 'string', example: 'PRO'),
                                ]
                            )
                        ),
                        new OA\Property(property: 'progress', type: 'integer', example: 33),
                    ]
                )
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function getVisaStatus(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'employee',
            'checklists' => fn($q) => $q->where('category_id', 1),
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to view this offboarding record.',
                403
            );
        }

        $employee = $offboarding->employee;
        $tasks = $offboarding->checklists;

        $totalTasks = $tasks->count();
        $completedTasks = $tasks->where('status', 'completed')->count();
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

        return $this->success([
            'offboarding_id' => $offboarding->id,
            'employee' => [
                'id' => $employee->id,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'employee_code' => $employee->employee_id,
            ],
            'visa_details' => [
                'visa_number' => $employee->visa_number,
                'visa_expiry_date' => $employee->visa_expiry_date,    // fixed: was visa_expiry
                'eid_number' => $employee->eid_number,           // fixed: was emirates_id_number
                'eid_expiry_date' => $employee->eid_expiry_date,      // fixed: was emirates_id_expiry
                'labor_number' => $employee->labor_number,         // fixed: was labour_card_number
                'visa_type' => $employee->visa_type,
            ],
            'cancellation' => [
                'status' => $offboarding->cancellation_status,
                'date' => $offboarding->cancellation_date,
                'reference' => $offboarding->cancellation_reference,
                'document' => $offboarding->cancellation_document,
                'remarks' => $offboarding->cancellation_remarks,
            ],
            'tasks' => $tasks,
            'progress' => $progress,
        ], 'Visa cancellation details fetched successfully.');
    }

    // -------------------------------------------------------------------------
    // UPDATE VISA STATUS (cancellation details)
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/visa-status',
        operationId: 'updateVisaStatus',
        summary: 'Save visa cancellation details',
        description: 'Stores the cancellation status, date, reference number, supporting document, and remarks for the visa cancellation stage.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(
                        property: 'cancellation_status',
                        type: 'string',
                        enum: ['pending', 'in_progress', 'completed', 'not_required'],
                        example: 'pending'
                    ),
                    new OA\Property(property: 'cancellation_date', type: 'string', format: 'date', nullable: true, example: '2026-08-20'),
                    new OA\Property(property: 'cancellation_reference', type: 'string', nullable: true, example: 'MOHRE-1234567'),
                    new OA\Property(property: 'cancellation_document', type: 'string', format: 'binary', nullable: true),
                    new OA\Property(property: 'cancellation_remarks', type: 'string', nullable: true, example: 'Submitted to GDRFA'),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Visa cancellation details updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Visa cancellation details updated successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function updateVisaStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'cancellation_status' => 'nullable|in:pending,in_progress,completed,not_required',
            'cancellation_date' => 'nullable|date',
            'cancellation_reference' => 'nullable|string|max:255',
            'cancellation_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'cancellation_remarks' => 'nullable|string',
        ]);

        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to update visa cancellation for this employee.',
                403
            );
        }

        $data = $request->only([
            'cancellation_status',
            'cancellation_date',
            'cancellation_reference',
            'cancellation_remarks',
        ]);

        // Handle optional file upload
        if ($request->hasFile('cancellation_document')) {
            $path = $request->file('cancellation_document')
                ->store('offboarding/visa-cancellation', 'public');
            $data['cancellation_document'] = $path;
        }

        $offboarding->update($data);

        return $this->success(
            $offboarding->fresh(),
            'Visa cancellation details updated successfully.'
        );
    }

    public function getSalaryPackages($id): JsonResponse
    {
        $employee = Employee::with('salaryPackages.salaryComponents')->findOrFail($id);
        return $this->success($employee, 'Salary packages fetched successfully.');
    }

    // -------------------------------------------------------------------------
    // COMPLETE VISA STATUS
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/visa-status/complete',
        operationId: 'completeVisaStatus',
        summary: 'Complete the visa cancellation stage',
        description: 'Marks the visa cancellation stage as completed and advances the offboarding status to `pending_checklist`. All visa_cancellation tasks must be completed or marked not_applicable first.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Visa cancellation stage completed successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Visa cancellation process completed successfully.'),
                new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Pending tasks still exist',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'All visa cancellation tasks must be completed before proceeding.'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function completeVisaStatus(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'checklists' => fn($q) => $q->where('category_id', 1),
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to complete visa cancellation for this employee.',
                403
            );
        }

        $pendingTasks = $offboarding->checklists
            ->where('status', 'pending')
            ->count();

        if ($pendingTasks > 0) {
            return $this->error(
                'All visa cancellation tasks must be completed before proceeding.',
                422
            );
        }

        $offboarding->update(['status' => 'pending_interview']);

        return $this->success(null, 'Visa cancellation process completed successfully.');
    }

    // -------------------------------------------------------------------------
    // UPDATE CHECKLIST
    // -------------------------------------------------------------------------

    #[OA\Patch(
        path: '/api/admin/offboarding/{id}/checklists',
        operationId: 'updateOffboardingChecklist',
        summary: 'Update the status of a checklist item',
        description: 'Updates the status of a single offboarding checklist task (any category_id). The task must belong to the given offboarding record.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['task_id', 'status'],
            properties: [
                new OA\Property(property: 'task_id', type: 'integer', example: 5),
                new OA\Property(
                    property: 'status',
                    type: 'string',
                    enum: ['pending', 'completed', 'not_applicable'],
                    example: 'completed'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Checklist item updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Checklist item updated successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 5),
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(property: 'category_id', type: 'string', example: 'general'),
                        new OA\Property(property: 'task_name', type: 'string', example: 'Return access card'),
                        new OA\Property(property: 'status', type: 'string', example: 'completed'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding or checklist item not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function updateChecklist(Request $request, $id): JsonResponse
    {
        $request->validate([
            'task_id' => 'nullable|exists:offboarding_checklists,id',
            'status' => 'required|in:pending,completed,not_applicable',
        ]);

        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to update checklist status for this employee.',
                403
            );
        }

        $task = OffboardingChecklist::where('id', $request->task_id)
            ->where('offboarding_id', $id)
            ->first();

        if (!$task) {
            return $this->error('Checklist item not found.', 404);
        }

        $task->update(['status' => $request->status]);

        return $this->success($task, 'Checklist item updated successfully.');
    }

    // -------------------------------------------------------------------------
    // UPDATE ASSETS
    // -------------------------------------------------------------------------

    #[OA\Patch(
        path: '/api/admin/offboarding/{id}/assets',
        operationId: 'updateAssets',
        summary: 'Update asset return status',
        description: 'Updates the return status and condition of one or more assets linked to an offboarding record.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['assets'],
            properties: [
                new OA\Property(
                    property: 'assets',
                    type: 'array',
                    items: new OA\Items(
                        required: ['id', 'status'],
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 3),
                            new OA\Property(property: 'status', type: 'string', example: 'Returned'),
                            new OA\Property(property: 'condition', type: 'string', nullable: true, example: 'Good'),
                        ]
                    )
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Assets updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Assets updated successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function updateAssets(Request $request, $offboarding_id): JsonResponse
    {
        $offboarding = Offboarding::find($offboarding_id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        foreach ($request->input('assets', []) as $asset) {
            EmployeeAsset::updateOrCreate(
                [
                    'id' => $asset['id'],
                    'employee_id' => $offboarding->employee->id,
                    'offboarding_id' => $offboarding->id,
                    'asset_name' => $asset['name'],
                    'status' => $asset['status'],
                    'condition' => $asset['return_condition'] ?? null,
                ]
            );
        }

        // If all assets are completed, move offboarding to next step
        if ($request->input('assets_status') === 'completed') {
            $offboarding->update([
                'status' => 'pending_settlement',
            ]);
        }

        return $this->success(
            [
                'assets' => EmployeeAsset::where('offboarding_id', $offboarding_id)->get(),
                'status' => $offboarding->status
            ],
            'Assets updated successfully.'
        );
    }

    // -------------------------------------------------------------------------
    // SUBMIT EXIT INTERVIEW
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/interview',
        operationId: 'submitInterview',
        summary: 'Submit or update the exit interview',
        description: 'Creates or updates the exit interview record for an offboarding process.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'interviewer', type: 'string', example: 'Fatima Al Zaabi (HR)'),
                new OA\Property(property: 'interview_date', type: 'string', format: 'date', example: '2026-06-17'),
                new OA\Property(property: 'interview_mode', type: 'string', example: 'In person'),
                new OA\Property(property: 'overall_satisfaction', type: 'string', example: 'Satisfied'),
                new OA\Property(property: 'primary_reason', type: 'string', example: 'Better opportunity'),
                new OA\Property(property: 'work_life_rating', type: 'string', nullable: true, example: '4'),
                new OA\Property(property: 'manager_relationship_rating', type: 'string', nullable: true, example: '5'),
                new OA\Property(property: 'enjoyed_most', type: 'string', nullable: true, example: 'Collaborative team culture'),
                new OA\Property(property: 'areas_for_improvement', type: 'string', nullable: true, example: 'Clearer promotion paths'),
                new OA\Property(property: 'would_recommend', type: 'boolean', example: true),
                new OA\Property(property: 'additional_comments', type: 'string', nullable: true, example: 'Would consider rejoining in the future.'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Exit interview submitted successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Exit interview submitted successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function submitInterview(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $interview = OffboardingInterview::updateOrCreate(
            ['offboarding_id' => $offboarding->id],
            $request->only([
                'interviewer',
                'interview_date',
                'interview_mode',
                'overall_satisfaction',
                'primary_reason',
                'work_life_rating',
                'manager_relationship_rating',
                'enjoyed_most',
                'areas_for_improvement',
                'would_recommend',
                'additional_comments'
            ])
        );

        $offboarding->update(['status' => 'pending_letters']);

        return $this->success($interview, 'Exit interview submitted successfully.');
    }

    // -------------------------------------------------------------------------
    // GET SETTLEMENT
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding/{id}/settlement',
        operationId: 'getSettlement',
        summary: 'Get the final settlement for an offboarding record (India)',
        description: 'Returns the final settlement record in INR, calculated leave encashment, Indian statutory gratuity (Payment of Gratuity Act, 1972), attendance summary, and salary packages for the given offboarding ID.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Settlement fetched successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Settlement fetched successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    nullable: true,
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 3),
                        new OA\Property(property: 'total_payable', type: 'number', format: 'float', example: 45000.00),
                        new OA\Property(property: 'total_deductions', type: 'number', format: 'float', example: 2000.00),
                        new OA\Property(property: 'net_payable', type: 'number', format: 'float', example: 43000.00),
                        new OA\Property(property: 'status', type: 'string', example: 'pending'),
                        new OA\Property(property: 'remarks', type: 'string', nullable: true),
                    ]
                ),
                new OA\Property(
                    property: 'calculated_settlement',
                    type: 'object',
                    description: 'Auto-derived calculation for final settlement in INR according to Indian norms.',
                    properties: [
                        new OA\Property(property: 'employee', type: 'object'),
                        new OA\Property(property: 'salary', type: 'object'),
                        new OA\Property(property: 'service_period', type: 'object', nullable: true),
                        new OA\Property(property: 'attendance', type: 'object'),
                        new OA\Property(property: 'leave', type: 'object'),
                        new OA\Property(property: 'leave_encashment', type: 'object'),
                        new OA\Property(property: 'gratuity', type: 'object'),
                        new OA\Property(property: 'overtime', type: 'object'),
                        new OA\Property(property: 'notice_period', type: 'object'),
                        new OA\Property(property: 'total_payable', type: 'number', format: 'float', example: 45000.00),
                        new OA\Property(property: 'total_deductions', type: 'number', format: 'float', example: 0.00),
                        new OA\Property(property: 'net_payable', type: 'number', format: 'float', example: 45000.00),
                    ]
                ),
                new OA\Property(
                    property: 'salary_components',
                    type: 'array',
                    items: new OA\Items(type: 'object')
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function getSettlement(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'employee.user.designation',
            'employee.user.department',
            'employee.salaryComponents',
            'settlement',
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $employee = $offboarding->employee;
        $salaryComponents = $employee->salaryComponents;

        $joiningDate = $employee->joining_date ? Carbon::parse($employee->joining_date) : null;
        $lastWorkingDay = $offboarding->last_working_day ? Carbon::parse($offboarding->last_working_day) : null;
        $settlementYear = $lastWorkingDay ? $lastWorkingDay->format('Y') : now()->format('Y');

        // ── Salary: basic + gross directly from employee's salary components in INR ──
        $grossSalary = (float) $salaryComponents->sum(fn($c) => (float) $c->value);

        $basicComponent = $salaryComponents->first(
            fn($component) => stripos($component->component_name, 'basic') !== false
        );
        $basicSalary = $basicComponent ? (float) $basicComponent->value : 0.0;

        // ── Service period ──
        $servicePeriod = null;
        $serviceYears = 0.0;
        if ($joiningDate && $lastWorkingDay) {
            $totalDays = $joiningDate->diffInDays($lastWorkingDay);
            $diff = $joiningDate->diff($lastWorkingDay);
            $serviceYears = round($totalDays / 365.25, 2);

            $servicePeriod = [
                'years' => $diff->y,
                'months' => $diff->m,
                'days' => $diff->d,
                'total_days' => $totalDays,
                'total_years' => $serviceYears,
            ];
        }

        // ── Working days vs. days worked in exit month (India) ──
        $workingDays = 0;
        $daysWorked = 0;
        if ($lastWorkingDay) {
            $periodStart = $lastWorkingDay->copy()->startOfMonth();
            if ($joiningDate && $joiningDate->gt($periodStart)) {
                $periodStart = $joiningDate->copy();
            }

            $workingDays = max(1, $periodStart->diffInWeekdays($lastWorkingDay) + 1);

            $daysWorked = AttendanceLog::where('userid', $employee->user_id)
                ->whereBetween('log_date', [$periodStart->toDateString(), $lastWorkingDay->toDateString()])
                ->where(function ($q) {
                    $q->whereNotNull('punch_in')
                        ->orWhere('log_status', 'out')
                        ->orWhere('status', 'present');
                })
                ->distinct('log_date')
                ->count('log_date');
        }

        // ── Leave: allocation, taken, unpaid leave, balance ──
        $leaveAllocated = LeaveAllocation::where('employee_id', $employee->id)
            ->where('year', $settlementYear)
            ->sum('allocated_days');

        $leaveTaken = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->sum('duration_days');

        $unpaidLeaveDays = LeaveRequest::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('claim_salary', false)
            ->sum('duration_days');

        $leaveBalanceDays = max(0, $leaveAllocated - $leaveTaken);

        // ── Leave encashment (INR) ──
        $perDaySalary = $grossSalary > 0 ? round($grossSalary / 30, 2) : 0.0;
        $leaveEncashmentAmount = round($perDaySalary * $leaveBalanceDays, 2);

        // ── Gratuity (Indian Payment of Gratuity Act, 1972: 15/26 * Basic * Completed Years, min 5 years service) ──
        $completedYearsForGratuity = 0;
        $isGratuityEligible = false;
        $gratuityAmount = 0.0;
        $dailyBasicSalary = $basicSalary > 0 ? round($basicSalary / 26, 2) : 0.0;

        if ($joiningDate && $lastWorkingDay) {
            $diff = $joiningDate->diff($lastWorkingDay);
            $fullYears = $diff->y;
            $months = $diff->m;
            $days = $diff->d;

            // In India, service > 6 months (>= 7 months) in final year rounds up to 1 completed year
            if ($months >= 7 || ($months == 6 && $days > 0)) {
                $completedYearsForGratuity = $fullYears + 1;
            } else {
                $completedYearsForGratuity = $fullYears;
            }

            // Indian Statutory Minimum Requirement: 5 completed years of continuous service
            if ($completedYearsForGratuity >= 5) {
                $isGratuityEligible = true;
                // Statutory Formula: (15 * Basic Salary * Completed Years) / 26
                $gratuityAmount = round((15 * $basicSalary * $completedYearsForGratuity) / 26, 2);

                // Statutory Tax-Free Limit in India: ₹20,00,000 (20 Lakhs INR)
                $maxGratuityCap = 2000000.00;
                $gratuityAmount = min($gratuityAmount, $maxGratuityCap);
            }
        }

        // ── Overtime owed: sum from payroll records not yet completed ──
        $overtimeAmount = (float) Payroll::where('user_id', $employee->user_id)
            ->where('status', '!=', 'completed')
            ->sum('overtime');

        // ── Notice period ──
        $noticePeriodDays = $offboarding->notice_period_days;
        $noticeStartDate = $offboarding->notice_start_date ? Carbon::parse($offboarding->notice_start_date) : null;
        $noticeEndDate = ($noticeStartDate && $noticePeriodDays)
            ? $noticeStartDate->copy()->addDays($noticePeriodDays)->toDateString()
            : null;
        $noticeDaysServed = ($noticeStartDate && $lastWorkingDay)
            ? max(0, $noticeStartDate->diffInDays($lastWorkingDay) + 1)
            : null;
        $noticeShortfallDays = ($noticePeriodDays !== null && $noticeDaysServed !== null)
            ? max(0, $noticePeriodDays - $noticeDaysServed)
            : null;

        // ── Totals (deductions default to 0) ──
        $totalDeductions = 0.0;
        $savedDeductions = $offboarding->settlement?->deductions ?? [];
        $totalDeductions = $offboarding->settlement ? (float) $offboarding->settlement->total_deductions : 0.0;
        $totalPayable = round($leaveEncashmentAmount + $gratuityAmount + $overtimeAmount, 2);
        $netPayable = round($totalPayable - $totalDeductions, 2);

        return $this->success([
            'settlement' => $offboarding->settlement,
            'calculated_settlement' => [
                'employee' => [
                    'id' => $employee->id,
                    'employee_id' => $employee->employee_id,
                    'name' => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                    'designation' => $employee->user->designation->name ?? null,
                    'department' => $employee->user->department->name ?? null,
                    'joining_date' => $employee->joining_date,
                    'last_working_day' => $offboarding->last_working_day,
                ],
                'salary' => [
                    'basic_salary' => round($basicSalary, 2),
                    'gross_salary' => round($grossSalary, 2),
                    'per_day_salary' => round($perDaySalary, 2),
                    'currency' => 'INR',
                ],
                'service_period' => $servicePeriod,
                'attendance' => [
                    'working_days' => $workingDays,
                    'days_worked' => $daysWorked,
                ],
                'leave' => [
                    'leave_allocated' => (float) $leaveAllocated,
                    'leave_taken' => (float) $leaveTaken,
                    'unpaid_leave_days' => (float) $unpaidLeaveDays,
                    'leave_balance_days' => (float) $leaveBalanceDays,
                ],
                'leave_encashment' => [
                    'per_day_salary' => round($perDaySalary, 2),
                    'leave_balance_days' => (float) $leaveBalanceDays,
                    'amount' => $leaveEncashmentAmount,
                ],
                'gratuity' => [
                    'eligible' => $isGratuityEligible,
                    'completed_years' => $completedYearsForGratuity,
                    'daily_basic_salary' => round($dailyBasicSalary, 2),
                    'formula' => '(15 * Basic Salary * Completed Years) / 26',
                    'amount' => $gratuityAmount,
                ],
                'overtime' => [
                    'amount' => round($overtimeAmount, 2),
                ],
                'notice_period' => [
                    'notice_period_days' => $noticePeriodDays,
                    'notice_start_date' => $offboarding->notice_start_date,
                    'notice_end_date' => $noticeEndDate,
                    'days_served' => $noticeDaysServed,
                    'shortfall_days' => $noticeShortfallDays,
                ],
                'total_payable' => $totalPayable,
                'total_deductions' => $totalDeductions,
                'net_payable' => $netPayable,
                'deductions' => $savedDeductions,
            ],
            'salary_components' => $salaryComponents,
        ], 'Settlement fetched successfully.');
    }

    // -------------------------------------------------------------------------
    // UPDATE SETTLEMENT
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/settlement',
        operationId: 'updateSettlement',
        summary: 'Create or update the final settlement',
        description: 'Creates or updates the final settlement record with deductions breakdown for an offboarding process.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: false,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'offboarding_id', type: 'integer', example: 8),
                new OA\Property(property: 'total_payable', type: 'number', format: 'float', example: 45000.00),
                new OA\Property(property: 'total_deductions', type: 'number', format: 'float', example: 10.00),
                new OA\Property(property: 'net_payable', type: 'number', format: 'float', example: 44990.00),
                new OA\Property(property: 'status', type: 'string', example: 'approved'),
                new OA\Property(property: 'settlement_status', type: 'string', example: 'approved'),
                new OA\Property(property: 'remarks', type: 'string', nullable: true),
                new OA\Property(
                    property: 'deductions',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'name', type: 'string', example: 'something'),
                            new OA\Property(property: 'amount', type: 'number', example: 10),
                            new OA\Property(property: 'currency', type: 'string', example: 'INR'),
                            new OA\Property(property: 'sort_order', type: 'integer', example: 1),
                        ]
                    )
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Settlement updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Settlement updated successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function updateSettlement(Request $request, $id = null): JsonResponse
    {
        $targetId = $id ?? $request->input('offboarding_id');

        if (!$targetId) {
            return $this->error('Offboarding ID is required.', 422);
        }

        $offboarding = Offboarding::find($targetId);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $status = $request->input('status') ?? $request->input('settlement_status') ?? 'pending';

        $settlement = OffboardingSettlement::updateOrCreate(
            ['offboarding_id' => $offboarding->id],
            [
                'total_payable' => (float) $request->input('total_payable', 0),
                'total_deductions' => (float) $request->input('total_deductions', 0),
                'net_payable' => (float) $request->input('net_payable', 0),
                'status' => $status,
                'remarks' => $request->input('remarks'),
                'deductions' => $request->input('deductions'),
            ]
        );

        if ($offboarding->status === 'pending_settlement') {
            $offboarding->update([
                'status' => 'pending_documentation',
            ]);
        }

        return $this->success($settlement, 'Settlement updated successfully.');
    }

    // -------------------------------------------------------------------------
    // GENERATE / UPDATE LETTERS
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/letters',
        operationId: 'generateLetters',
        summary: 'Generate or update an offboarding letter record',
        description: 'Creates or updates a letter record (e.g. experience letter, NOC, visa cancellation letter) for an offboarding process.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['letter_type'],
            properties: [
                new OA\Property(property: 'letter_type', type: 'string', example: 'experience_letter'),
                new OA\Property(property: 'document_path', type: 'string', nullable: true, example: 'letters/exp_letter_emp2.pdf'),
                new OA\Property(property: 'status', type: 'string', example: 'pending'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Letter record updated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Letter record updated successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function generateLetters(Request $request, $id): JsonResponse
    {
        $request->validate([
            'letter_type' => 'required|string|in:experience_letter,noc,relieving_letter,final_settlement,resignation_acceptance',
            'status' => 'nullable|string',
        ]);

        $offboarding = Offboarding::with([
            'employee.user.company',
            'employee.user.designation',
            'employee.user.department',
            'reportingManager.user.designation',
            'settlement',
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $letterType = $request->input('letter_type');

        /*
        |--------------------------------------------------------------------------
        | Map letter_type → Blade view
        |--------------------------------------------------------------------------
        */
        $viewMap = [
            'experience_letter' => 'pdf.offboarding.experience_letter',
            'noc' => 'pdf.offboarding.noc',
            'relieving_letter' => 'pdf.offboarding.relieving_letter',
            'final_settlement' => 'pdf.offboarding.final_settlement',
            'resignation_acceptance' => 'pdf.offboarding.resignation_acceptance',
        ];

        $view = $viewMap[$letterType] ?? null;

        if (!$view || !view()->exists($view)) {
            return $this->error("Template not found for letter type: {$letterType}", 500);
        }

        try {
            /*
            |----------------------------------------------------------------------
            | Generate PDF
            |----------------------------------------------------------------------
            */
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, [
                'offboarding' => $offboarding,
            ])->setPaper('a4', 'portrait');

            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', false);
            $pdf->setOption('defaultFont', 'DejaVu Sans');

            /*
            |----------------------------------------------------------------------
            | Save to storage/app/public/offboarding/letters/
            |----------------------------------------------------------------------
            */
            $filename = "{$letterType}_offboarding_{$offboarding->id}_" . now()->format('YmdHis') . '.pdf';
            $storedPath = 'offboarding/letters/' . $filename;

            \Storage::disk('public')->put($storedPath, $pdf->output());

            /*
            |----------------------------------------------------------------------
            | Persist the OffboardingLetter record
            |----------------------------------------------------------------------
            */
            $letter = OffboardingLetter::updateOrCreate(
                [
                    'offboarding_id' => $offboarding->id,
                    'letter_type' => $letterType,
                ],
                [
                    'document_path' => $storedPath,
                    'status' => $request->input('status', 'generated'),
                ]
            );

            $letter->document_url = \Storage::disk('public')->url($storedPath);

            return $this->success($letter, 'Letter generated successfully.');
        } catch (\Exception $e) {
            \Log::error("Failed to generate {$letterType} for offboarding {$offboarding->id}: " . $e->getMessage());
            return $this->error('Failed to generate letter: ' . $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------
    // UPLOAD LETTER FILE
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/letters/upload',
        operationId: 'uploadOffboardingLetter',
        summary: 'Upload a letter document for an offboarding record',
        description: 'Accepts a file upload (PDF, DOCX, JPG, PNG) for a specific letter type and stores it. Creates or updates the OffboardingLetter record with the stored file path.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['letter_type', 'file'],
                properties: [
                    new OA\Property(
                        property: 'letter_type',
                        type: 'string',
                        example: 'experience_letter',
                        description: 'Type of the letter (e.g. experience_letter, noc, visa_cancellation)'
                    ),
                    new OA\Property(
                        property: 'file',
                        type: 'string',
                        format: 'binary',
                        description: 'Document file (PDF, DOCX, JPG, PNG – max 10 MB)'
                    ),
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Letter file uploaded successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Letter uploaded successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function uploadLetter(Request $request, $id): JsonResponse
    {
        $request->validate([
            'letter_type' => 'required|string|max:100',
            'file' => 'required|file|mimes:pdf,docx,jpg,jpeg,png|max:10240',
        ]);

        $offboarding = Offboarding::find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error('Unauthorized access', 403);
        }

        $letterType = $request->input('letter_type');
        $file = $request->file('file');

        // Build a deterministic filename:
        //   offboarding_{id}_{letter_type}_{timestamp}.{ext}
        $ext = $file->getClientOriginalExtension();
        $filename = "offboarding_{$offboarding->id}_{$letterType}_" . now()->format('YmdHis') . ".{$ext}";

        // Store in storage/app/public/offboarding/letters/
        $storedPath = $file->storeAs('offboarding/letters', $filename, 'public');

        // Update or create the letter record
        $letter = OffboardingLetter::updateOrCreate(
            [
                'offboarding_id' => $offboarding->id,
                'letter_type' => $letterType,
            ],
            [
                'document_path' => $storedPath,
                'status' => 'uploaded',
            ]
        );

        $letter->document_url = \Storage::disk('public')->url($storedPath);

        return $this->success($letter, 'Letter uploaded successfully.');
    }

    // -------------------------------------------------------------------------
    // COMPLETE LETTERS & DOCUMENTS
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/letters/complete',
        operationId: 'updateLetters',
        summary: 'Complete the letters & documents stage',
        description: 'Marks the letters & documents stage as completed and advances the offboarding status to `pending_final`. At least one letter must already be generated or uploaded.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Letters & documents stage completed successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Letters & documents stage completed successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'No letters generated yet',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'At least one letter must be generated or uploaded before proceeding.'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function updateLetters(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with(['letters'])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to complete the letters stage for this employee.',
                403
            );
        }

        if (!$this->isLettersGenerated($offboarding)) {
            return $this->error(
                'At least one letter must be generated or uploaded before proceeding.',
                422
            );
        }

        $offboarding->update(['status' => 'completed']);

        return $this->success($offboarding->fresh(), 'Letters & documents stage completed successfully.');
    }

    // -------------------------------------------------------------------------
    // GET PROGRESS
    // -------------------------------------------------------------------------

    #[OA\Get(
        path: '/api/admin/offboarding/{id}/progress',
        operationId: 'getOffboardingProgress',
        summary: 'Get overall offboarding progress',
        description: 'Returns a breakdown of all offboarding steps with their individual status and an overall completion percentage.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding progress retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Offboarding progress fetched successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'offboarding_id', type: 'integer', example: 1),
                        new OA\Property(property: 'current_status', type: 'string', example: 'pending_checklist'),
                        new OA\Property(property: 'completed_steps', type: 'integer', example: 2),
                        new OA\Property(property: 'total_steps', type: 'integer', example: 7),
                        new OA\Property(property: 'progress_percentage', type: 'integer', example: 29),
                        new OA\Property(
                            property: 'steps',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'name', type: 'string', example: 'Visa Cancellation'),
                                    new OA\Property(property: 'key', type: 'string', example: 'visa'),
                                    new OA\Property(
                                        property: 'status',
                                        type: 'string',
                                        enum: ['pending', 'in_progress', 'completed'],
                                        example: 'completed'
                                    ),
                                ]
                            )
                        ),
                    ]
                ),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function getProgress(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'checklists',
            'interview',
            'settlement',
            'assets',
            'letters',
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to view this offboarding record.',
                403
            );
        }

        $steps = $this->getOffboardingSteps($offboarding);
        $completedSteps = collect($steps)->where('status', 'completed')->count();
        $totalSteps = count($steps);

        return $this->success([
            'offboarding_id' => $offboarding->id,
            'current_status' => $offboarding->status,
            'completed_steps' => $completedSteps,
            'total_steps' => $totalSteps,
            'progress_percentage' => $totalSteps > 0
                ? round(($completedSteps / $totalSteps) * 100)
                : 0,
            'steps' => $steps,
            'progress_steps' => $steps,
        ], 'Offboarding progress fetched successfully.');
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Build the ordered step-status array for an offboarding record.
     * Relations that must be eager-loaded: checklists, assets, interview,
     * settlement, letters.
     */
    private function getOffboardingSteps(Offboarding $offboarding): array
    {
        return [
            [
                'name' => 'Initiation',
                'key' => 'initiation',
                'status' => $offboarding->id ? 'completed' : 'pending',
            ],
            [
                'name' => 'Handover',
                'key' => 'handover',
                'status' => $offboarding->status === 'pending_handover'
                    ? 'in_progress'
                    : ($offboarding->status === 'pending_leave_check' ||
                        $offboarding->status === 'pending_access' ||
                        $offboarding->status === 'pending_settlement' ||
                        $offboarding->status === 'pending_documentation' ||
                        $offboarding->status === 'completed'
                        ? 'completed'
                        : 'pending'),
            ],
            [
                'name' => 'Leave Check',
                'key' => 'leave_check',
                'status' => $offboarding->status === 'pending_leave_check'
                    ? 'in_progress'
                    : (in_array($offboarding->status, [
                        'pending_access',
                        'pending_settlement',
                        'pending_documentation',
                        'completed',
                    ])
                        ? 'completed'
                        : 'pending'),
            ],
            [
                'name' => 'Access Removal',
                'key' => 'access',
                'status' => $offboarding->status === 'pending_access'
                    ? 'in_progress'
                    : (in_array($offboarding->status, [
                        'pending_settlement',
                        'pending_documentation',
                        'completed',
                    ])
                        ? 'completed'
                        : 'pending'),
            ],
            [
                'name' => 'Final Settlement',
                'key' => 'settlement',
                'status' => $offboarding->status === 'pending_settlement'
                    ? 'in_progress'
                    : (in_array($offboarding->status, [
                        'pending_documentation',
                        'completed',
                    ])
                        ? 'completed'
                        : 'pending'),
            ],
            [
                'name' => 'Documentation',
                'key' => 'documentation',
                'status' => $offboarding->status === 'pending_documentation'
                    ? 'in_progress'
                    : ($offboarding->status === 'completed'
                        ? 'completed'
                        : 'pending'),
            ],
            [
                'name' => 'Completed Offboarding',
                'key' => 'completed',
                'status' => $offboarding->status === 'completed'
                    ? 'completed'
                    : 'pending',
            ],
        ];
    }


    // -------------------------------------------------------------------------
    // COMPLETE OFFBOARDING
    // -------------------------------------------------------------------------

    #[OA\Post(
        path: '/api/admin/offboarding/{id}/complete',
        operationId: 'completeOffboarding',
        summary: 'Mark the offboarding process as completed',
        description: 'Marks the offboarding record\'s status as `completed`. All steps (visa cancellation, checklist, exit interview, final settlement, letters, asset return) must already be completed.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding marked as completed',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Offboarding marked as completed successfully.'),
                new OA\Property(property: 'data', type: 'object'),
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Pending steps still exist',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'All offboarding steps must be completed first: Final Settlement, Letters & Documents.'),
            ]
        )
    )]
    #[OA\Response(response: 403, description: 'Unauthorized')]
    #[OA\Response(response: 404, description: 'Offboarding record not found')]
    public function completeOffboarding(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'interview',
            'settlement',
            'assets',
            'letters',
        ])->find($id);

        if (!$offboarding) {
            return $this->error('Offboarding record not found.', 404);
        }

        if (!$this->isAuthorized($request->user(), $offboarding->employee)) {
            return $this->error(
                'You are not authorized to complete this offboarding record.',
                403
            );
        }

        $pendingSteps = collect($this->getOffboardingSteps($offboarding))
            ->where('key', '!=', 'final')
            ->where('status', '!=', 'completed')
            ->pluck('name');

        if ($pendingSteps->isNotEmpty()) {
            return $this->error(
                'All offboarding steps must be completed first: ' .
                $pendingSteps->implode(', ') . '.',
                422
            );
        }

        $offboarding->update(['status' => 'completed']);
        // Deactivate the employee's user account
        if ($offboarding->employee?->user) {
            $offboarding->employee->user->update([
                'status' => 'offboarding'
            ]);
        }

        return $this->success($offboarding->fresh(), 'Offboarding marked as completed successfully.');
    }

    // -------------------------------------------------------------------------
    // DELETE OFFBOARDING
    // -------------------------------------------------------------------------

    #[OA\Delete(
        path: '/api/admin/offboarding/{id}',
        operationId: 'deleteOffboarding',
        summary: 'Delete an offboarding record',
        description: 'Deletes an offboarding record and its related checklist, assets, interview, settlement, and letter records.',
        security: [['bearerAuth' => []]],
        tags: ['Offboarding']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Offboarding ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Offboarding deleted successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'success',
                    type: 'boolean',
                    example: true
                ),
                new OA\Property(
                    property: 'message',
                    type: 'string',
                    example: 'Offboarding record deleted successfully.'
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 404,
        description: 'Offboarding record not found'
    )]
    public function destroy(Request $request, $id): JsonResponse
    {
        $offboarding = Offboarding::with([
            'employee',
            'checklists',
            'assets',
            'interview',
            'settlement',
            'letters',
        ])->find($id);

        if (!$offboarding) {
            return $this->error(
                'Offboarding record not found.',
                404
            );
        }

        // Check authorization
        if (
            !$this->isAuthorized(
                $request->user(),
                $offboarding->employee
            )
        ) {
            return $this->error(
                'You are not authorized to delete this offboarding record.',
                403
            );
        }

        try {

            \DB::transaction(function () use ($offboarding) {

                try {
                    $offboarding->checklists()->delete();
                } catch (\Exception $e) {
                    throw new \Exception('Checklists delete failed: ' . $e->getMessage());
                }

                try {
                    $offboarding->handover()?->delete();
                } catch (\Exception $e) {
                    throw new \Exception('Handover delete failed: ' . $e->getMessage());
                }

                try {
                    $offboarding->leaveVerification()?->delete();
                } catch (\Exception $e) {
                    throw new \Exception('Leave verification delete failed: ' . $e->getMessage());
                }

                try {
                    $offboarding->accessRemoval()?->delete();
                } catch (\Exception $e) {
                    throw new \Exception('Access removal delete failed: ' . $e->getMessage());
                }

                try {
                    $offboarding->assets()->delete();
                } catch (\Exception $e) {
                    throw new \Exception('Assets delete failed: ' . $e->getMessage());
                }

                try {
                    $offboarding->interview()->delete();
                } catch (\Exception $e) {
                    throw new \Exception('Interview delete failed: ' . $e->getMessage());
                }

                try {
                    $offboarding->settlement()->delete();
                } catch (\Exception $e) {
                    throw new \Exception('Settlement delete failed: ' . $e->getMessage());
                }

                try {
                    $offboarding->letters()->delete();
                } catch (\Exception $e) {
                    throw new \Exception('Letters delete failed: ' . $e->getMessage());
                }

                AssetAssignment::where(
                    'employee_id',
                    $offboarding->employee_id
                )->delete();

                $employee = Employee::where('id', $offboarding->employee_id)
                    ->whereHas('user', function ($query) {
                        $query->where('status', 'offboarding');
                    })
                    ->first();

                if ($employee && $employee->user) {
                    $employee->user()->update([
                        'status' => 'active',
                    ]);
                }

                $offboarding->delete();
            });

            return $this->success(
                null,
                'Offboarding record deleted successfully.'
            );
        } catch (\Exception $e) {

            \Log::error(
                "Failed to delete offboarding {$offboarding->id}: "
                . $e->getMessage()
            );

            return $this->error(
                'Failed to delete offboarding record: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Check if at least one letter has been generated or uploaded for offboarding.
     */
    private function isLettersGenerated(Offboarding $offboarding): bool
    {
        return $offboarding->letters()
            ->where(function ($query) {
                $query->whereNotNull('document_path')
                    ->where('document_path', '!=', '')
                    ->orWhereIn('status', ['generated', 'uploaded', 'completed']);
            })
            ->exists();
    }

    /**
     * Check if all assets have been returned for offboarding.
     */
    private function isAssetsReturned(Offboarding $offboarding): bool
    {
        $assets = $offboarding->assets;

        if ($assets->isEmpty()) {
            return true;
        }

        return $assets->every(function ($asset) {
            return in_array(strtolower($asset->status ?? ''), ['returned', 'collected', 'completed', 'n/a', 'not_applicable']);
        });
    }

    /**
     * Check if final settlement has been completed/approved.
     */
    private function isSettlementCompleted(Offboarding $offboarding): bool
    {
        return $offboarding->settlement && in_array(strtolower($offboarding->settlement->status ?? ''), ['approved', 'completed']);
    }

    /**
     * Check if visa cancellation stage is completed.
     */
    private function isVisaCompleted(Offboarding $offboarding): bool
    {
        if (strtolower($offboarding->visa_sponsorship ?? '') === 'none' || strtolower($offboarding->cancellation_status ?? '') === 'completed') {
            return true;
        }

        $visaChecklists = $offboarding->checklists()->where('category_id', 1)->get();

        if ($visaChecklists->isEmpty()) {
            return true;
        }

        return $visaChecklists->every(function ($task) {
            return in_array(strtolower($task->status ?? ''), ['completed', 'not_applicable']);
        });
    }

    /**
     * Check if exit interview is completed.
     */
    private function isInterviewCompleted(Offboarding $offboarding): bool
    {
        return $offboarding->interview()->exists();
    }

    /**
     * Check if the requesting user is authorised to manage this offboarding.
     * Admins and HR Managers always pass; other users pass only if they are
     * the reporting manager of the employee.
     */
    private function isAuthorized($user, $employee): bool
    {
        if ($user->type === 'admin' || $user->role?->name === 'HR Manager') {
            return true;
        }

        return $user->employee
            && $employee->reporting_manager_id === $user->employee->id;
    }

    /**
     * Return the multiplier to convert a given currency into AED.
     * Rates are approximate and should be updated periodically if live rates
     * are not available.  AED and unknown currencies default to 1.0.
     */
    private function toAedRate(string $currency): float
    {
        return match (strtoupper(trim($currency))) {
            'AED' => 1.0,
            'USD' => 3.6725,   // 1 USD = 3.6725 AED (fixed peg)
            'INR' => 0.04375,  // approx 1 INR = 0.04375 AED
            'EUR' => 3.97,     // approx
            'GBP' => 4.63,     // approx
            'SAR' => 0.98,     // approx
            'QAR' => 1.01,     // approx
            'KWD' => 12.02,    // approx
            'BHD' => 9.75,     // approx
            'OMR' => 9.54,     // approx
            'PKR' => 0.013,    // approx
            'NPR' => 0.028,    // approx
            'EGP' => 0.075,    // approx
            'PHP' => 0.064,    // approx
            'LKR' => 0.011,    // approx
            'BDT' => 0.033,    // approx
            default => 1.0,     // treat unknown currencies as AED
        };
    }
}
