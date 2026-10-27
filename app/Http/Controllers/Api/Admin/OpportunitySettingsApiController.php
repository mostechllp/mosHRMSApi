<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\OpportunityLostReason;
use App\Models\OpportunityStage;
use App\Models\OpportunityType;
use App\Models\OpportunityWinReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Opportunity Settings API Controller
 *
 * Manages the four opportunity configuration panels:
 *   - Opportunity Stages       (name + color + default_probability + sort_order)
 *   - Lost Reasons             (name + sort_order)
 *   - Win Reasons              (name + sort_order)
 *   - Opportunity Types        (name + sort_order)
 *
 * All endpoints are prefixed with /api/admin/crm/opportunity-settings/
 */
class OpportunitySettingsApiController extends ApiController
{
    // ──────────────────────────────────────────────────────────────────────────
    //  OVERVIEW / BULK GET & SEED
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return all four setting groups in one call.
     * GET /api/admin/crm/opportunity-settings
     */
    public function index(): JsonResponse
    {
        [$stages, $lostReasons, $winReasons, $types] = $this->fetchAll();

        return $this->success([
            'stages' => $stages,
            'lost_reasons' => $lostReasons,
            'win_reasons' => $winReasons,
            'types' => $types,
        ], 'Opportunity settings retrieved successfully');
    }

    /**
     * Seed default values for all four groups (only inserts missing ones).
     * POST /api/admin/crm/opportunity-settings/seed-defaults
     */
    public function seedDefaults(): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Stages
            foreach (OpportunityStage::DEFAULTS as $item) {
                OpportunityStage::firstOrCreate(['name' => $item['name']], array_merge($item, [
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]));
            }

            // Lost Reasons
            foreach (OpportunityLostReason::DEFAULTS as $i => $name) {
                OpportunityLostReason::firstOrCreate(['name' => $name], [
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }

            // Win Reasons
            foreach (OpportunityWinReason::DEFAULTS as $i => $name) {
                OpportunityWinReason::firstOrCreate(['name' => $name], [
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }

            // Types
            foreach (OpportunityType::DEFAULTS as $i => $name) {
                OpportunityType::firstOrCreate(['name' => $name], [
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }

            DB::commit();

            [$stages, $lostReasons, $winReasons, $types] = $this->fetchAll();

            return $this->success([
                'stages' => $stages,
                'lost_reasons' => $lostReasons,
                'win_reasons' => $winReasons,
                'types' => $types,
            ], 'Default opportunity settings seeded successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to seed defaults: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  OPPORTUNITY STAGES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/opportunity-settings/stages
     */
    public function indexStages(): JsonResponse
    {
        try {
            $stages = OpportunityStage::orderBy('sort_order')->orderBy('id')->get();
            return $this->success($stages, 'Opportunity stages retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve opportunity stages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/opportunity-settings/stages
     */
    public function storeStage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:opportunity_stages,name',
            'color' => 'nullable|string|max:30',
            'default_probability' => 'nullable|numeric|between:0,100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();
            $validated['color'] = $validated['color'] ?? '#6366F1';
            $validated['default_probability'] = $validated['default_probability'] ?? 0;
            $validated['sort_order'] = $validated['sort_order'] ?? (OpportunityStage::max('sort_order') + 1);

            $stage = OpportunityStage::create($validated);
            return $this->success($stage, 'Opportunity stage created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create opportunity stage: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/opportunity-settings/stages/{id}
     */
    public function showStage($id): JsonResponse
    {
        $stage = OpportunityStage::find($id);
        if (!$stage) {
            return $this->error('Opportunity stage not found', 404);
        }
        return $this->success($stage, 'Opportunity stage retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/opportunity-settings/stages/{id}
     */
    public function updateStage(Request $request, $id): JsonResponse
    {
        $stage = OpportunityStage::find($id);
        if (!$stage) {
            return $this->error('Opportunity stage not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:opportunity_stages,name,' . $id,
            'color' => 'nullable|string|max:30',
            'default_probability' => 'nullable|numeric|between:0,100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['updated_by'] = Auth::id();
            $stage->update($validated);
            return $this->success($stage->fresh(), 'Opportunity stage updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update opportunity stage: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/opportunity-settings/stages/{id}
     */
    public function destroyStage($id): JsonResponse
    {
        $stage = OpportunityStage::find($id);
        if (!$stage) {
            return $this->error('Opportunity stage not found', 404);
        }

        try {
            $stage->delete();
            return $this->success(null, 'Opportunity stage deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete opportunity stage: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  LOST REASONS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/opportunity-settings/lost-reasons
     */
    public function indexLostReasons(): JsonResponse
    {
        try {
            $reasons = OpportunityLostReason::orderBy('sort_order')->orderBy('name')->get();
            
            return $this->success($reasons, 'Lost reasons retrieved successfully');
        } catch (\Throwable $e) {
            return $this->success($this->defaultLostReasons(), 'Lost reasons retrieved (defaults)');
        }
    }

    /**
     * POST /api/admin/crm/opportunity-settings/lost-reasons
     */
    public function storeLostReason(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:opportunity_lost_reasons,name',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();
            $validated['sort_order'] = $validated['sort_order'] ?? (OpportunityLostReason::max('sort_order') + 1);

            $reason = OpportunityLostReason::create($validated);
            return $this->success($reason, 'Lost reason created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create lost reason: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/opportunity-settings/lost-reasons/{id}
     */
    public function showLostReason($id): JsonResponse
    {
        $reason = OpportunityLostReason::find($id);
        if (!$reason) {
            return $this->error('Lost reason not found', 404);
        }
        return $this->success($reason, 'Lost reason retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/opportunity-settings/lost-reasons/{id}
     */
    public function updateLostReason(Request $request, $id): JsonResponse
    {
        $reason = OpportunityLostReason::find($id);
        if (!$reason) {
            return $this->error('Lost reason not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150|unique:opportunity_lost_reasons,name,' . $id,
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['updated_by'] = Auth::id();
            $reason->update($validated);
            return $this->success($reason->fresh(), 'Lost reason updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update lost reason: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/opportunity-settings/lost-reasons/{id}
     */
    public function destroyLostReason($id): JsonResponse
    {
        $reason = OpportunityLostReason::find($id);
        if (!$reason) {
            return $this->error('Lost reason not found', 404);
        }

        try {
            $reason->delete();
            return $this->success(null, 'Lost reason deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete lost reason: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  WIN REASONS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/opportunity-settings/win-reasons
     */
    public function indexWinReasons(): JsonResponse
    {
        try {
            $reasons = OpportunityWinReason::orderBy('sort_order')->orderBy('name')->get();
            
            return $this->success($reasons, 'Win reasons retrieved successfully');
        } catch (\Throwable $e) {
            return $this->success($this->defaultWinReasons(), 'Win reasons retrieved (defaults)');
        }
    }

    /**
     * POST /api/admin/crm/opportunity-settings/win-reasons
     */
    public function storeWinReason(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:opportunity_win_reasons,name',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();
            $validated['sort_order'] = $validated['sort_order'] ?? (OpportunityWinReason::max('sort_order') + 1);

            $reason = OpportunityWinReason::create($validated);
            return $this->success($reason, 'Win reason created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create win reason: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/opportunity-settings/win-reasons/{id}
     */
    public function showWinReason($id): JsonResponse
    {
        $reason = OpportunityWinReason::find($id);
        if (!$reason) {
            return $this->error('Win reason not found', 404);
        }
        return $this->success($reason, 'Win reason retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/opportunity-settings/win-reasons/{id}
     */
    public function updateWinReason(Request $request, $id): JsonResponse
    {
        $reason = OpportunityWinReason::find($id);
        if (!$reason) {
            return $this->error('Win reason not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:150|unique:opportunity_win_reasons,name,' . $id,
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['updated_by'] = Auth::id();
            $reason->update($validated);
            return $this->success($reason->fresh(), 'Win reason updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update win reason: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/opportunity-settings/win-reasons/{id}
     */
    public function destroyWinReason($id): JsonResponse
    {
        $reason = OpportunityWinReason::find($id);
        if (!$reason) {
            return $this->error('Win reason not found', 404);
        }

        try {
            $reason->delete();
            return $this->success(null, 'Win reason deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete win reason: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  OPPORTUNITY TYPES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/opportunity-settings/types
     */
    public function indexTypes(): JsonResponse
    {
        try {
            $types = OpportunityType::orderBy('sort_order')->orderBy('name')->get();           
            return $this->success($types, 'Opportunity types retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve opportunity types: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/opportunity-settings/types
     */
    public function storeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:opportunity_types,name',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();
            $validated['sort_order'] = $validated['sort_order'] ?? (OpportunityType::max('sort_order') + 1);

            $type = OpportunityType::create($validated);
            return $this->success($type, 'Opportunity type created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create opportunity type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/opportunity-settings/types/{id}
     */
    public function showType($id): JsonResponse
    {
        $type = OpportunityType::find($id);
        if (!$type) {
            return $this->error('Opportunity type not found', 404);
        }
        return $this->success($type, 'Opportunity type retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/opportunity-settings/types/{id}
     */
    public function updateType(Request $request, $id): JsonResponse
    {
        $type = OpportunityType::find($id);
        if (!$type) {
            return $this->error('Opportunity type not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:opportunity_types,name,' . $id,
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['updated_by'] = Auth::id();
            $type->update($validated);
            return $this->success($type->fresh(), 'Opportunity type updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update opportunity type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/opportunity-settings/types/{id}
     */
    public function destroyType($id): JsonResponse
    {
        $type = OpportunityType::find($id);
        if (!$type) {
            return $this->error('Opportunity type not found', 404);
        }

        try {
            $type->delete();
            return $this->success(null, 'Opportunity type deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete opportunity type: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchAll(): array
    {
        $stages = OpportunityStage::orderBy('sort_order')->orderBy('id')->get();
        $lostReasons = OpportunityLostReason::orderBy('sort_order')->orderBy('name')->get();
        $winReasons = OpportunityWinReason::orderBy('sort_order')->orderBy('name')->get();
        $types = OpportunityType::orderBy('sort_order')->orderBy('name')->get();

        return [$stages, $lostReasons, $winReasons, $types];
    }

    private function defaultStages(): array
    {
        return array_map(fn($item, $i) => array_merge($item, ['id' => $i + 1]), OpportunityStage::DEFAULTS, array_keys(OpportunityStage::DEFAULTS));
    }

    private function defaultLostReasons(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name, 'sort_order' => $i + 1], OpportunityLostReason::DEFAULTS, array_keys(OpportunityLostReason::DEFAULTS));
    }

    private function defaultWinReasons(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name, 'sort_order' => $i + 1], OpportunityWinReason::DEFAULTS, array_keys(OpportunityWinReason::DEFAULTS));
    }

    private function defaultTypes(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name, 'sort_order' => $i + 1], OpportunityType::DEFAULTS, array_keys(OpportunityType::DEFAULTS));
    }
}
