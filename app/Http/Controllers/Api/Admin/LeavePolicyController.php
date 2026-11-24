<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeavePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeavePolicyController extends Controller
{
    /**
     * List leave policies
     */
    public function index()
    {
        $policies = LeavePolicy::with('leaveType')
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Leave policies retrieved successfully.',
            'data' => $policies,
        ]);
    }

    /**
     * Create leave policy
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_type_id' => [
                'required',
                'integer',
                'exists:leave_types,id',
                'unique:leave_policies,leave_type_id',
            ],

            'annual_allocation' => [
                'required',
                'numeric',
                'min:0',
            ],

            'enable_accrual' => [
                'required',
                'boolean',
            ],

            'accrual_type' => [
                'nullable',
                Rule::in([
                    'monthly',
                    'quarterly',
                    'half_yearly',
                    'yearly',
                    'daily',
                ]),
            ],

            'accrual_days' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'apply_during_probation' => [
                'required',
                'boolean',
            ],

            'probation_action' => [
                'nullable',
                Rule::in([
                    'hold',
                    'accrue',
                    'accrue_and_restrict_usage',
                ]),
            ],

            'release_after_probation' => [
                'required',
                'boolean',
            ],

            'enable_carry_forward' => [
                'required',
                'boolean',
            ],

            'unlimited_carry_forward' => [
                'required',
                'boolean',
            ],

            'maximum_carry_forward' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'status' => [
                'nullable',
                'boolean',
            ],
        ]);

        if ($validated['enable_accrual']) {

            if (empty($validated['accrual_type'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Accrual type is required when accrual is enabled.',
                ], 422);
            }

            if (
                !isset($validated['accrual_days']) ||
                $validated['accrual_days'] <= 0
            ) {
                return response()->json([
                    'status' => false,
                    'message' => 'Accrual days must be greater than 0.',
                ], 422);
            }
        }

        if (
            $validated['enable_carry_forward'] &&
            !$validated['unlimited_carry_forward'] &&
            empty($validated['maximum_carry_forward'])
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Maximum carry forward is required.',
            ], 422);
        }

        $policy = LeavePolicy::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Leave policy created successfully.',
            'data' => $policy->load('leaveType'),
        ], 201);
    }

    /**
     * Show policy
     */
    public function show($id)
    {
        $policy = LeavePolicy::with('leaveType')
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $policy,
        ]);
    }

    /**
     * Update policy
     */
    public function update(Request $request, $id)
    {
        $policy = LeavePolicy::findOrFail($id);

        $validated = $request->validate([
            'leave_type_id' => [
                'required',
                'integer',
                'exists:leave_types,id',
                Rule::unique('leave_policies', 'leave_type_id')
                    ->ignore($policy->id),
            ],

            'annual_allocation' => [
                'required',
                'numeric',
                'min:0',
            ],

            'enable_accrual' => [
                'required',
                'boolean',
            ],

            'accrual_type' => [
                'nullable',
                Rule::in([
                    'monthly',
                    'quarterly',
                    'half_yearly',
                    'yearly',
                    'daily',
                ]),
            ],

            'accrual_days' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'apply_during_probation' => [
                'required',
                'boolean',
            ],

            'probation_action' => [
                'nullable',
                Rule::in([
                    'hold',
                    'accrue',
                    'accrue_and_restrict_usage',
                ]),
            ],

            'release_after_probation' => [
                'required',
                'boolean',
            ],

            'enable_carry_forward' => [
                'required',
                'boolean',
            ],

            'unlimited_carry_forward' => [
                'required',
                'boolean',
            ],

            'maximum_carry_forward' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'status' => [
                'nullable',
                'boolean',
            ],
        ]);

        $policy->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Leave policy updated successfully.',
            'data' => $policy->load('leaveType'),
        ]);
    }

    /**
     * Delete policy
     */
    public function destroy($id)
    {
        $policy = LeavePolicy::findOrFail($id);

        $policy->delete();

        return response()->json([
            'status' => true,
            'message' => 'Leave policy deleted successfully.',
        ]);
    }
}