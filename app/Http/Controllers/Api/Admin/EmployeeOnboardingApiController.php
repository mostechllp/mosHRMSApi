<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Employee;
use App\Models\EmployeePreOnboardingChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Employee Onboarding',
    description: 'Endpoints for employee onboarding process including details, salary, banks, and completion'
)]
class EmployeeOnboardingApiController extends ApiController
{
    /**
     * Get basic employee onboarding details
     */
    #[OA\Get(
        path: '/api/admin/employees/onboard/details/{id}',
        operationId: 'getOnboardingDetails',
        summary: 'Get onboarding employee details',
        description: 'Fetch onboarding employee details by Employee ID or User ID.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'user_id', in: 'query', required: false, description: 'User ID or Employee ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Employee details retrieved successfully')]
    #[OA\Response(response: 404, description: 'Employee details not found')]
    public function getDetails(Request $request, $id = null): JsonResponse
    {
        $targetId = $id ?? $request->id ?? $request->user_id ?? $request->employee_id;

        if (!$targetId) {
            return $this->error('Employee ID or User ID is required', 400);
        }

        $employee = Employee::with([
            'user.department',
            'user.designation',
            'user.organization',
            'user.company',
            'salaryComponents',
            'bankDetails',
            'verification'
        ])
            ->where('id', $targetId)
            ->orWhere('user_id', $targetId)
            ->orWhere('employee_id', $targetId)
            ->first();

        if (!$employee) {
            return $this->error('Employee details not found', 404);
        }

        return $this->success($employee, 'Employee details retrieved successfully');
    }

    /**
     * Save basic employee details
     */
    #[OA\Post(
        path: '/api/admin/employees/onboard/details/{id}',
        operationId: 'saveOnboardingDetails',
        summary: 'Save/create basic employee onboarding details',
        description: 'Create or update onboarding basic employee details.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Employee details saved successfully')]
    public function saveDetails(Request $request, $id = null): JsonResponse
    {
        $user_id = $id ?? $request->user_id ?? $request->id ?? $request->employee_id;
        $employee = null;

        if ($user_id) {
            $employee = Employee::where('user_id', $user_id)
                ->orWhere('id', $user_id)
                ->orWhere('employee_id', $user_id)
                ->first();

            if (!$employee) {
                return $this->error('Employee not found', 404);
            }
        } else {
            $employee = new Employee();
        }

        $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'personal_email' => 'nullable|email|max:255',
            'personal_number' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'joining_date' => 'nullable|date',
            'experience_level' => 'nullable|string|max:255',
            'key_skills' => 'nullable|string',
            'highest_education' => 'nullable|string|max:255',
            'department_id' => 'nullable|integer|exists:departments,id',
            'designation_id' => 'nullable|integer|exists:designations,id',
            'special_days' => 'nullable|array',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string|max:255',
            'marital_status' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, &$employee) {
            $employeeData = $request->only([
                'first_name',
                'last_name',
                'personal_email',
                'personal_number',
                'nationality',
                'address',
                'joining_date',
                'experience_level',
                'key_skills',
                'highest_education',
                'dob',
                'gender',
                'marital_status',
            ]);

            if ($request->has('special_days')) {
                $employeeData['special_days'] = $request->special_days;
            }

            if (!$employee->exists) {
                // Generate a temporary user email if not provided
                $userEmail = $request->personal_email ?? 'temp_' . \Illuminate\Support\Str::random(8) . '@example.com';

                // Create a User record
                $user = \App\Models\User::create([
                    'username' => $userEmail,
                    'email' => $userEmail,
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(10)),
                    'organization_id' => $request->organization_id ?? 1,
                    'company_id' => $request->company_id ?? null,
                    'type' => 'employee',
                    'status' => 'onboarding',
                ]);

                $employeeData['user_id'] = $user->id;
                $employeeData['employee_id'] = 'EMP-' . strtoupper(\Illuminate\Support\Str::random(6));
                $employeeData['company_email'] = $userEmail;

                if (empty($employeeData['personal_email'])) {
                    $employeeData['personal_email'] = $userEmail;
                }

                if (empty($employeeData['first_name'])) {
                    $employeeData['first_name'] = 'Draft';
                }

                $employee->fill($employeeData);
                $employee->save();
            } else {
                $employee->update($employeeData);
            }

            if ($employee->user) {
                $userData = [];
                if ($request->has('department_id'))
                    $userData['department_id'] = $request->department_id;
                if ($request->has('designation_id'))
                    $userData['designation_id'] = $request->designation_id;

                if (!empty($userData)) {
                    $employee->user->update($userData);
                }
            }
        });

        return $this->success(
            $employee->fresh()->load('user.department', 'user.designation', 'salaryComponents', 'bankDetails', 'verification'),
            'Employee details saved successfully'
        );
    }

    /**
     * Update basic employee details
     */
    #[OA\Put(
        path: '/api/admin/employees/onboard/details/{id}',
        operationId: 'updateOnboardingDetails',
        summary: 'Update basic employee onboarding details',
        description: 'Update onboarding details for an existing employee.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Employee details updated successfully')]
    public function updateDetails(Request $request, $id = null): JsonResponse
    {
        $targetId = $id ?? $request->id ?? $request->user_id ?? $request->employee_id;

        if ($targetId) {
            $request->merge(['user_id' => $targetId]);
        }

        return $this->saveDetails($request, $id);
    }

    /**
     * Get employee professional verification details
     */
    #[OA\Get(
        path: '/api/admin/employees/onboard/verification/{id}',
        operationId: 'getOnboardingVerification',
        summary: 'Get employee professional verification details',
        description: 'Fetch professional verification links and checklist status for an employee.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Verification details retrieved successfully')]
    #[OA\Response(response: 404, description: 'Employee not found')]
    public function getVerification(Request $request, $id = null): JsonResponse
    {
        $targetId = $id ?? $request->id ?? $request->user_id ?? $request->employee_id;

        if (!$targetId) {
            return $this->error('Employee ID or User ID is required', 400);
        }

        $employee = Employee::with('verification')
            ->where('id', $targetId)
            ->orWhere('user_id', $targetId)
            ->orWhere('employee_id', $targetId)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        return $this->success($employee->verification ?? (object) [], 'Verification details retrieved successfully');
    }

    /**
     * Save employee professional verification details
     */
    #[OA\Post(
        path: '/api/admin/employees/onboard/verification/{id}',
        operationId: 'saveOnboardingVerification',
        summary: 'Save professional verification details',
        description: 'Save or update employee professional URLs, verification checklist, and notes.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Verification details saved successfully')]
    public function saveVerification(Request $request, $id = null): JsonResponse
    {
        $user_id = $id ?? $request->user_id ?? $request->id ?? $request->employee_id;

        if (!$user_id) {
            return $this->error('User ID or Employee ID is required', 400);
        }

        $employee = Employee::where('user_id', $user_id)
            ->orWhere('id', $user_id)
            ->orWhere('employee_id', $user_id)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        $request->validate([
            'linkedin_url' => 'nullable|url|max:255',
            'github_url' => 'nullable|url|max:255',
            'portfolio_url' => 'nullable|url|max:255',
            'other_professional_url' => 'nullable|url|max:255',
            'identity_verified' => 'nullable|boolean',
            'credentials_verified' => 'nullable|boolean',
            'employment_info_verified' => 'nullable|boolean',
            'verification_notes' => 'nullable|string',
        ]);

        $verificationData = $request->only([
            'linkedin_url',
            'github_url',
            'portfolio_url',
            'other_professional_url',
            'identity_verified',
            'credentials_verified',
            'employment_info_verified',
            'verification_notes',
        ]);

        $verification = $employee->verification()->updateOrCreate(
            ['employee_id' => $employee->id],
            $verificationData
        );

        return $this->success($verification, 'Verification details saved successfully');
    }

    /**
     * Update employee professional verification details
     */
    #[OA\Put(
        path: '/api/admin/employees/onboard/verification/{id}',
        operationId: 'updateOnboardingVerification',
        summary: 'Update professional verification details',
        description: 'Update employee professional URLs, verification checklist, and notes.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Verification details updated successfully')]
    public function updateVerification(Request $request, $id = null): JsonResponse
    {
        return $this->saveVerification($request, $id);
    }

    /**
     * Get salary structure details
     */
    #[OA\Get(
        path: '/api/admin/employees/onboard/salary/{id}',
        operationId: 'getOnboardingSalary',
        summary: 'Get salary structure details',
        description: 'Retrieve employee salary currency, payment cycle, and salary components during onboarding.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Salary details retrieved successfully')]
    #[OA\Response(response: 404, description: 'Employee not found')]
    public function getSalary(Request $request, $id = null): JsonResponse
    {
        $targetId = $id ?? $request->id ?? $request->user_id ?? $request->employee_id;

        if (!$targetId) {
            return $this->error('Employee ID or User ID is required', 400);
        }

        $employee = Employee::with('salaryComponents')
            ->where('id', $targetId)
            ->orWhere('user_id', $targetId)
            ->orWhere('employee_id', $targetId)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        return $this->success([
            'employee_id' => $employee->id,
            'currency' => $employee->currency,
            'payment_cycle' => $employee->payment_cycle,
            'salary_components' => $employee->salaryComponents,
        ], 'Salary details retrieved successfully');
    }

    /**
     * Save salary structure details
     */
    #[OA\Post(
        path: '/api/admin/employees/onboard/salary/{id}',
        operationId: 'saveOnboardingSalary',
        summary: 'Save salary structure details',
        description: 'Save or update employee salary components and structure during onboarding.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Salary details saved successfully')]
    public function saveSalary(Request $request, $id = null): JsonResponse
    {
        $user_id = $id ?? $request->user_id ?? $request->id ?? $request->employee_id;

        if (!$user_id) {
            return $this->error('User ID or Employee ID is required', 400);
        }

        $employee = Employee::where('user_id', $user_id)
            ->orWhere('id', $user_id)
            ->orWhere('employee_id', $user_id)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        $request->validate([
            'currency' => 'required|string|max:255',
            'payment_cycle' => 'required|string|max:255',
            'salary_components' => 'nullable|array',
            'salary_components.*.component_name' => 'required|string|max:255',
            'salary_components.*.value' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $employee) {
            $employee->update([
                'currency' => $request->currency,
                'payment_cycle' => $request->payment_cycle,
            ]);

            // Clear old components and insert new ones
            $employee->salaryComponents()->delete();

            if ($request->has('salary_components') && is_array($request->salary_components)) {
                foreach ($request->salary_components as $component) {
                    $employee->salaryComponents()->create([
                        'component_name' => $component['component_name'],
                        'value' => $component['value']
                    ]);
                }
            }
        });

        return $this->success($employee->fresh()->load('salaryComponents'), 'Salary details saved successfully');
    }

    /**
     * Get bank details
     */
    #[OA\Get(
        path: '/api/admin/employees/onboard/banks/{id}',
        operationId: 'getOnboardingBanks',
        summary: 'Get bank details',
        description: 'Retrieve employee bank accounts during onboarding.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Bank details retrieved successfully')]
    #[OA\Response(response: 404, description: 'Employee not found')]
    public function getBanks(Request $request, $id = null): JsonResponse
    {
        $targetId = $id ?? $request->id ?? $request->user_id ?? $request->employee_id;

        if (!$targetId) {
            return $this->error('Employee ID or User ID is required', 400);
        }

        $employee = Employee::with('bankDetails')
            ->where('id', $targetId)
            ->orWhere('user_id', $targetId)
            ->orWhere('employee_id', $targetId)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        return $this->success($employee->bankDetails, 'Bank details retrieved successfully');
    }

    /**
     * Save bank details
     */
    #[OA\Post(
        path: '/api/admin/employees/onboard/banks/{id}',
        operationId: 'saveOnboardingBanks',
        summary: 'Save bank details',
        description: 'Save or update employee bank accounts during onboarding.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Bank details saved successfully')]
    public function saveBanks(Request $request, $id = null): JsonResponse
    {
        $user_id = $id ?? $request->user_id ?? $request->id ?? $request->employee_id;

        if (!$user_id) {
            return $this->error('User ID or Employee ID is required', 400);
        }

        $employee = Employee::where('user_id', $user_id)
            ->orWhere('id', $user_id)
            ->orWhere('employee_id', $user_id)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        $request->validate([
            'bank_details' => 'nullable|array',
            'bank_details.*.bank_country' => 'required|string|max:255',
            'bank_details.*.bank_name' => 'required|string|max:255',
            'bank_details.*.account_number' => 'required|string|max:255',
            'bank_details.*.iban_number' => 'nullable|string|max:255',
            'bank_details.*.swift_code' => 'nullable|string|max:255',
            'bank_details.*.branch_name' => 'nullable|string|max:255',
            'bank_details.*.ifsc_code' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $employee) {
            // Clear old banks and insert new ones
            $employee->bankDetails()->delete();

            if ($request->has('bank_details') && is_array($request->bank_details)) {
                foreach ($request->bank_details as $bank) {
                    $employee->bankDetails()->create($bank);
                }
            }
        });

        return $this->success($employee->fresh()->load('bankDetails'), 'Bank details saved successfully');
    }

    /**
     * Get Pre-Onboarding Checklist
     */
    #[OA\Get(
        path: '/api/admin/employees/onboard/checklist/{id}',
        operationId: 'getPreOnboardingChecklist',
        summary: 'Get pre-onboarding checklist details',
        description: 'Fetch pre-onboarding checklist data by Employee ID or User ID.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Pre-onboarding checklist retrieved successfully')]
    #[OA\Response(response: 404, description: 'Employee not found')]
    public function getChecklist(Request $request, $id = null): JsonResponse
    {
        $user_id = $id ?? $request->user_id ?? $request->id ?? $request->employee_id;

        if (!$user_id) {
            return $this->error('User ID or Employee ID is required', 400);
        }

        $employee = Employee::with('preOnboardingChecklist')
            ->where('id', $user_id)
            ->orWhere('user_id', $user_id)
            ->orWhere('employee_id', $user_id)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        return $this->success($employee->preOnboardingChecklist, 'Pre-onboarding checklist retrieved successfully');
    }

    /**
     * Save/Update Pre-Onboarding Checklist
     */
    #[OA\Post(
        path: '/api/admin/employees/onboard/checklist/{id}',
        operationId: 'savePreOnboardingChecklist',
        summary: 'Save or update pre-onboarding checklist',
        description: 'Save HR tasks, IT tasks, WhatsApp groups, Welcome poster, and Google Meet introduction checklist items.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Pre-onboarding checklist saved successfully')]
    public function saveChecklist(Request $request, $id = null): JsonResponse
    {
        $user_id = $id ?? $request->user_id ?? $request->id ?? $request->employee_id;

        if (!$user_id) {
            return $this->error('User ID or Employee ID is required', 400);
        }

        $employee = Employee::where('id', $user_id)
            ->orWhere('user_id', $user_id)
            ->orWhere('employee_id', $user_id)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        $validated = $request->validate([
            // HR Tasks
            'hr_emp_info_completed' => 'nullable|boolean',
            'hr_id_proof_verified' => 'nullable|boolean',
            'hr_academic_cert_verified' => 'nullable|boolean',
            'hr_employment_ref_verified' => 'nullable|boolean',
            'hr_all_docs_verified' => 'nullable|boolean',
            'hr_offer_letter_generated' => 'nullable|boolean',
            'hr_offer_letter_sent' => 'nullable|boolean',
            'hr_offer_letter_accepted' => 'nullable|boolean',
            // IT Tasks
            'it_company_email_created' => 'nullable|boolean',
            'it_hrms_account_created' => 'nullable|boolean',
            'it_system_access_created' => 'nullable|boolean',
            'it_software_configured' => 'nullable|boolean',
            // Section 1 - WhatsApp Groups
            'wa_personal_added' => 'nullable|boolean',
            'wa_personal_added_date' => 'nullable|date',
            'wa_personal_added_by' => 'nullable|string|max:255',
            'wa_team_added' => 'nullable|boolean',
            'wa_team_added_date' => 'nullable|date',
            'wa_team_added_by' => 'nullable|string|max:255',
            // Section 2 - Welcome Announcement
            'welcome_poster_published' => 'nullable|boolean',
            'welcome_poster_published_date' => 'nullable|date',
            'welcome_poster_published_by' => 'nullable|string|max:255',
            // Section 3 - Google Meet Introduction
            'meet_date' => 'nullable|date',
            'meet_time' => 'nullable|string|max:255',
            'meet_link' => 'nullable|string|max:255',
            'meet_calendar_invite_sent' => 'nullable|boolean',
            'meet_completed' => 'nullable|boolean',
        ]);

        $checklist = EmployeePreOnboardingChecklist::updateOrCreate(
            ['employee_id' => $employee->id],
            $validated
        );

        return $this->success($checklist, 'Pre-onboarding checklist saved successfully');
    }

    /**
     * Complete Onboarding
     */
    #[OA\Post(
        path: '/api/admin/employees/onboard/complete/{id}',
        operationId: 'completeOnboarding',
        summary: 'Complete Onboarding',
        description: 'Finalize onboarding process for an employee.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Onboarding completed successfully')]
    public function complete(Request $request, $id = null): JsonResponse
    {
        $user_id = $id ?? $request->user_id ?? $request->id ?? $request->employee_id;

        if (!$user_id) {
            return $this->error('User ID or Employee ID is required', 400);
        }

        $employee = Employee::where('user_id', $user_id)
            ->orWhere('id', $user_id)
            ->orWhere('employee_id', $user_id)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        if ($employee->user) {
            $randomPassword = \Illuminate\Support\Str::random(10);

            $employee->user->update([
                'status' => 'onboarding',
                'password' => \Illuminate\Support\Facades\Hash::make($randomPassword)
            ]);

            $recipient = $employee->company_email ?: $employee->personal_email;
            if ($recipient) {
                try {
                    \Illuminate\Support\Facades\Mail::to($recipient)->send(new \App\Mail\UserRegistrationMail($employee->user, $randomPassword, $employee));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send registration email: ' . $e->getMessage());
                }
            }
        }

        return $this->success($employee->fresh()->load('user', 'salaryComponents', 'bankDetails', 'preOnboardingChecklist'), 'Onboarding completed successfully');
    }

    /**
     * Get onboarding progress
     */
    #[OA\Get(
        path: '/api/admin/employees/onboard/progress/{id}',
        operationId: 'getOnboardingProgress',
        summary: 'Get onboarding progress',
        description: 'Returns step-by-step completion status and overall percentage for an employee onboarding.',
        security: [['bearerAuth' => []]],
        tags: ['Employee Onboarding']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: false, description: 'Employee ID or User ID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Onboarding progress retrieved successfully')]
    #[OA\Response(response: 404, description: 'Employee not found')]
    public function getProgress(Request $request, $id = null): JsonResponse
    {
        $targetId = $id ?? $request->id ?? $request->user_id ?? $request->employee_id;

        if (!$targetId) {
            return $this->error('Employee ID or User ID is required', 400);
        }

        $employee = Employee::with([
            'user.department',
            'user.designation',
            'salaryComponents',
            'bankDetails',
            'verification',
            'preOnboardingChecklist',
        ])
            ->where('id', $targetId)
            ->orWhere('user_id', $targetId)
            ->orWhere('employee_id', $targetId)
            ->first();

        if (!$employee) {
            return $this->error('Employee not found', 404);
        }

        // Step 1: Basic Details — check key personal fields are filled
        $detailsComplete = !empty($employee->first_name)
            && $employee->first_name !== 'Draft'
            && !empty($employee->last_name)
            && (!empty($employee->personal_email) || !empty($employee->company_email))
            && $employee->user
            && !empty($employee->user->department_id)
            && !empty($employee->user->designation_id);

        // Step 2: Professional Verification
        $verificationComplete = $employee->verification !== null
            && (
                !empty($employee->verification->linkedin_url)
                || !empty($employee->verification->github_url)
                || !empty($employee->verification->portfolio_url)
                || !empty($employee->verification->other_professional_url)
            )
            && $employee->verification->identity_verified
            && $employee->verification->credentials_verified
            && $employee->verification->employment_info_verified;

        // Step 3: Salary — currency, payment_cycle and at least one component
        $salaryComplete = !empty($employee->currency)
            && !empty($employee->payment_cycle)
            && $employee->salaryComponents->isNotEmpty();

        // Step 4: Bank Details — at least one bank added
        $banksComplete = $employee->bankDetails->isNotEmpty();

        // Step 5: Pre-Onboarding Checklist
        $checklistComplete = $employee->preOnboardingChecklist !== null;

        // Step 6: Completed — user status moved beyond 'onboarding'
        $isCompleted = $employee->user && in_array($employee->user->status, ['active', 'onboarding']);

        $steps = [
            [
                'step' => 1,
                'key' => 'details',
                'label' => 'Basic Details',
                'completed' => $detailsComplete,
                'endpoint' => 'GET /api/admin/employees/onboard/details/' . $employee->id,
            ],
            [
                'step' => 2,
                'key' => 'verification',
                'label' => 'Professional Verification',
                'completed' => $verificationComplete,
                'endpoint' => 'GET /api/admin/employees/onboard/verification/' . $employee->id,
            ],
            [
                'step' => 3,
                'key' => 'salary',
                'label' => 'Salary Structure',
                'completed' => $salaryComplete,
                'endpoint' => 'GET /api/admin/employees/onboard/salary/' . $employee->id,
            ],
            [
                'step' => 4,
                'key' => 'banks',
                'label' => 'Bank Details',
                'completed' => $banksComplete,
                'endpoint' => 'GET /api/admin/employees/onboard/banks/' . $employee->id,
            ],
            [
                'step' => 5,
                'key' => 'checklist',
                'label' => 'Pre-Onboarding Checklist',
                'completed' => $checklistComplete,
                'endpoint' => 'GET /api/admin/employees/onboard/checklist/' . $employee->id,
            ],
            [
                'step' => 6,
                'key' => 'complete',
                'label' => 'Complete Onboarding',
                'completed' => $isCompleted,
                'endpoint' => 'POST /api/admin/employees/onboard/complete/' . $employee->id,
            ],
        ];

        $completedCount = collect($steps)->where('completed', true)->count();
        $totalSteps = count($steps);
        $percentage = (int) round(($completedCount / $totalSteps) * 100);

        return $this->success([
            'employee_id' => $employee->id,
            'employee_name' => trim($employee->first_name . ' ' . $employee->last_name),
            'status' => $employee->user?->status ?? 'onboarding',
            'percentage' => $percentage,
            'completed_steps' => $completedCount,
            'total_steps' => $totalSteps,
            'steps' => $steps,
        ], 'Onboarding progress retrieved successfully');
    }
}

