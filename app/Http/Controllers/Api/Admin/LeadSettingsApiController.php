<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\LeadPriority;
use App\Models\LeadQualificationRule;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lead Settings API Controller
 *
 * Manages the four lead configuration panels visible in the UI:
 *   - Lead Statuses       (name + colour dot + edit/delete)
 *   - Lead Sources        (name + edit/delete)
 *   - Lead Priorities     (name + colour dot + edit/delete)
 *   - Lead Qualification Rules (rule text + checkbox + delete)
 *
 * All endpoints are prefixed with /api/admin/crm/lead-settings/
 */
class LeadSettingsApiController extends ApiController
{
    // ──────────────────────────────────────────────────────────────────────────
    //  OVERVIEW / BULK GET
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return all four setting groups in one call (useful for the full settings page).
     * GET /api/admin/crm/lead-settings
     */
    public function index(): JsonResponse
    {
        [$statuses, $sources, $priorities, $rules] = $this->fetchAll();

        return $this->success([
            'statuses' => $statuses,
            'sources' => $sources,
            'priorities' => $priorities,
            'qualification_rules' => $rules,
        ], 'Lead settings retrieved successfully');
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  LEAD STATUSES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/lead-settings/statuses
     */
    public function indexStatuses(): JsonResponse
    {
            $statuses = LeadStatus::orderBy('sort_order')->orderBy('name')->get();
          
            return $this->success($statuses, 'Lead statuses retrieved successfully');
        
    }

    /**
     * POST /api/admin/crm/lead-settings/statuses
     */

    public function storeStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:lead_statuses,name',
            'color' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            $status = DB::transaction(function () use ($validated) {
                if (!empty($validated['is_default'])) {
                    $defaultExists = LeadStatus::where('is_default', true)
                        ->lockForUpdate()
                        ->exists();

                    if ($defaultExists) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'is_default' => [
                                'A default lead status already exists. You cannot add another default status.',
                            ],
                        ]);
                    }
                }

                $validated['created_by'] = Auth::id();
                $validated['updated_by'] = Auth::id();
                $validated['color'] = $validated['color'] ?? '#6366F1';
                $validated['sort_order'] = $validated['sort_order']
                    ?? ((LeadStatus::max('sort_order') ?? -1) + 1);

                return LeadStatus::create($validated);
            });

            return $this->success(
                $status,
                'Lead status created successfully',
                201
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->error(
                'Failed to create lead status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * GET /api/admin/crm/lead-settings/statuses/{id}
     */
    public function showStatus($id): JsonResponse
    {
        $status = LeadStatus::find($id);
        if (!$status) {
            return $this->error('Lead status not found', 404);
        }
        return $this->success($status, 'Lead status retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/lead-settings/statuses/{id}
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $status = LeadStatus::find($id);
        if (!$status) {
            return $this->error('Lead status not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:lead_statuses,name,' . $id,
            'color' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            $validated['updated_by'] = Auth::id();
            $status->update($validated);
            return $this->success($status->fresh(), 'Lead status updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update lead status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/lead-settings/statuses/{id}
     */
    public function destroyStatus($id): JsonResponse
    {
        $status = LeadStatus::find($id);
        if (!$status) {
            return $this->error('Lead status not found', 404);
        }

        if ($status->is_default) {
            return $this->error('Cannot delete the default lead status', 422);
        }

        try {
            $status->delete();
            return $this->success(null, 'Lead status deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete lead status: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  LEAD SOURCES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/lead-settings/sources
     */
    public function indexSources(): JsonResponse
    {
            $sources = LeadSource::orderBy('sort_order')->orderBy('name')->get();
            return $this->success($sources, 'Lead sources retrieved successfully');
    }

    /**
     * POST /api/admin/crm/lead-settings/sources
     */
    public function storeSource(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:lead_sources,name',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();
            $validated['sort_order'] = $validated['sort_order'] ?? (LeadSource::max('sort_order') + 1);

            $source = LeadSource::create($validated);
            return $this->success($source, 'Lead source created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create lead source: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/lead-settings/sources/{id}
     */
    public function showSource($id): JsonResponse
    {
        $source = LeadSource::find($id);
        if (!$source) {
            return $this->error('Lead source not found', 404);
        }
        return $this->success($source, 'Lead source retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/lead-settings/sources/{id}
     */
    public function updateSource(Request $request, $id): JsonResponse
    {
        $source = LeadSource::find($id);
        if (!$source) {
            return $this->error('Lead source not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:lead_sources,name,' . $id,
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['updated_by'] = Auth::id();
            $source->update($validated);
            return $this->success($source->fresh(), 'Lead source updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update lead source: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/lead-settings/sources/{id}
     */
    public function destroySource($id): JsonResponse
    {
        $source = LeadSource::find($id);
        if (!$source) {
            return $this->error('Lead source not found', 404);
        }

        try {
            $source->delete();
            return $this->success(null, 'Lead source deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete lead source: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  LEAD PRIORITIES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/lead-settings/priorities
     */
    public function indexPriorities(): JsonResponse
    {
        $priorities = LeadPriority::orderBy('sort_order')->orderBy('name')->get();
           
        return $this->success($priorities, 'Lead priorities retrieved successfully');
    }

    /**
     * POST /api/admin/crm/lead-settings/priorities
     */
    public function storePriority(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:lead_priorities,name',
            'color' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();
            $validated['color'] = $validated['color'] ?? '#F59E0B';
            $validated['sort_order'] = $validated['sort_order'] ?? (LeadPriority::max('sort_order') + 1);

            $priority = LeadPriority::create($validated);
            return $this->success($priority, 'Lead priority created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create lead priority: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/lead-settings/priorities/{id}
     */
    public function showPriority($id): JsonResponse
    {
        $priority = LeadPriority::find($id);
        if (!$priority) {
            return $this->error('Lead priority not found', 404);
        }
        return $this->success($priority, 'Lead priority retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/lead-settings/priorities/{id}
     */
    public function updatePriority(Request $request, $id): JsonResponse
    {
        $priority = LeadPriority::find($id);
        if (!$priority) {
            return $this->error('Lead priority not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:lead_priorities,name,' . $id,
            'color' => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['updated_by'] = Auth::id();
            $priority->update($validated);
            return $this->success($priority->fresh(), 'Lead priority updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update lead priority: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/lead-settings/priorities/{id}
     */
    public function destroyPriority($id): JsonResponse
    {
        $priority = LeadPriority::find($id);
        if (!$priority) {
            return $this->error('Lead priority not found', 404);
        }

        try {
            $priority->delete();
            return $this->success(null, 'Lead priority deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete lead priority: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  LEAD QUALIFICATION RULES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/lead-settings/qualification-rules
     */
    public function indexQualificationRules(): JsonResponse
    {
        $rules = LeadQualificationRule::orderBy('sort_order')->orderBy('id')->get();
        return $this->success($rules, 'Qualification rules retrieved successfully');
    }

    /**
     * POST /api/admin/crm/lead-settings/qualification-rules
     */
    public function storeQualificationRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rule' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();
            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['sort_order'] = $validated['sort_order'] ?? (LeadQualificationRule::max('sort_order') + 1);

            $rule = LeadQualificationRule::create($validated);
            return $this->success($rule, 'Qualification rule created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create qualification rule: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/lead-settings/qualification-rules/{id}
     */
    public function showQualificationRule($id): JsonResponse
    {
        $rule = LeadQualificationRule::find($id);
        if (!$rule) {
            return $this->error('Qualification rule not found', 404);
        }
        return $this->success($rule, 'Qualification rule retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/lead-settings/qualification-rules/{id}
     */
    public function updateQualificationRule(Request $request, $id): JsonResponse
    {
        $rule = LeadQualificationRule::find($id);
        if (!$rule) {
            return $this->error('Qualification rule not found', 404);
        }

        $validated = $request->validate([
            'rule' => 'sometimes|required|string|max:255',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        try {
            $validated['updated_by'] = Auth::id();
            $rule->update($validated);
            return $this->success($rule->fresh(), 'Qualification rule updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update qualification rule: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/admin/crm/lead-settings/qualification-rules/{id}/toggle
     * Quickly flip the is_active checkbox shown in the UI.
     */
    public function toggleQualificationRule($id): JsonResponse
    {
        $rule = LeadQualificationRule::find($id);
        if (!$rule) {
            return $this->error('Qualification rule not found', 404);
        }

        try {
            $rule->is_active = !$rule->is_active;
            $rule->updated_by = Auth::id();
            $rule->save();
            return $this->success($rule, 'Qualification rule toggled successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to toggle qualification rule: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/lead-settings/qualification-rules/{id}
     */
    public function destroyQualificationRule($id): JsonResponse
    {
        $rule = LeadQualificationRule::find($id);
        if (!$rule) {
            return $this->error('Qualification rule not found', 404);
        }

        try {
            $rule->delete();
            return $this->success(null, 'Qualification rule deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete qualification rule: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  SEED DEFAULTS endpoint
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Seed default values for all four groups (only inserts missing ones).
     * POST /api/admin/crm/lead-settings/seed-defaults
     */
    public function seedDefaults(): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Statuses
            foreach (LeadStatus::DEFAULTS as $item) {
                LeadStatus::firstOrCreate(['name' => $item['name']], array_merge($item, [
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]));
            }

            // Sources
            foreach (LeadSource::DEFAULTS as $i => $name) {
                LeadSource::firstOrCreate(['name' => $name], [
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }

            // Priorities
            foreach (LeadPriority::DEFAULTS as $item) {
                LeadPriority::firstOrCreate(['name' => $item['name']], array_merge($item, [
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]));
            }

            // Qualification Rules
            foreach (LeadQualificationRule::DEFAULTS as $item) {
                LeadQualificationRule::firstOrCreate(['rule' => $item['rule']], array_merge($item, [
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]));
            }

            DB::commit();

            [$statuses, $sources, $priorities, $rules] = $this->fetchAll();

            return $this->success([
                'statuses' => $statuses,
                'sources' => $sources,
                'priorities' => $priorities,
                'qualification_rules' => $rules,
            ], 'Default lead settings seeded successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to seed defaults: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchAll(): array
    {

        $statuses = LeadStatus::orderBy('sort_order')->orderBy('name')->get();
        $sources = LeadSource::orderBy('sort_order')->orderBy('name')->get();
        $priorities = LeadPriority::orderBy('sort_order')->orderBy('name')->get();
        $rules = LeadQualificationRule::orderBy('sort_order')->orderBy('name')->get();
        return [$statuses, $sources, $priorities, $rules];
    }

    private function defaultStatuses(): array
    {
        return array_map(fn($item, $i) => array_merge($item, ['id' => $i + 1]), LeadStatus::DEFAULTS, array_keys(LeadStatus::DEFAULTS));
    }

    private function defaultSources(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name, 'sort_order' => $i + 1], LeadSource::DEFAULTS, array_keys(LeadSource::DEFAULTS));
    }

    private function defaultPriorities(): array
    {
        return array_map(fn($item, $i) => array_merge($item, ['id' => $i + 1]), LeadPriority::DEFAULTS, array_keys(LeadPriority::DEFAULTS));
    }

    private function defaultQualificationRules(): array
    {
        return array_map(fn($item, $i) => array_merge($item, ['id' => $i + 1]), LeadQualificationRule::DEFAULTS, array_keys(LeadQualificationRule::DEFAULTS));
    }
}
