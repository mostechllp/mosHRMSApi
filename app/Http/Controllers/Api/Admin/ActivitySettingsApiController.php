<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\ActivityPriority;
use App\Models\ActivityReminderOption;
use App\Models\ActivitySetting;
use App\Models\ActivityType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Activity Settings API Controller
 *
 * Manages the four activity configuration panels:
 *   - Activity Types             (Call, Email, Meeting, Task, Site Visit)
 *   - Reminder Options           (None [0 min], 15 min [15 min], 1 hour [60 min], 1 day [1440 min])
 *   - Priority Values            (Low, Medium, High with color indicators)
 *   - Default Activity Duration  (e.g., 60 minutes)
 *
 * All endpoints are prefixed with /api/admin/crm/activity-settings/
 */
class ActivitySettingsApiController extends ApiController
{
    // ──────────────────────────────────────────────────────────────────────────
    //  OVERVIEW / BULK GET & SEED
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return all activity settings in one call.
     * GET /api/admin/crm/activity-settings
     */
    public function index(): JsonResponse
    {
        [$types, $reminders, $priorities, $defaultDuration] = $this->fetchAll();

        return $this->success([
            'activity_types' => $types,
            'reminder_options' => $reminders,
            'priorities' => $priorities,
            'default_activity_duration' => $defaultDuration,
        ], 'Activity settings retrieved successfully');
    }

    /**
     * Seed default values for all groups.
     * POST /api/admin/crm/activity-settings/seed-defaults
     */
    public function seedDefaults(): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Types
            foreach (ActivityType::DEFAULTS as $name) {
                ActivityType::firstOrCreate(['name' => $name]);
            }

            // Reminders
            foreach (ActivityReminderOption::DEFAULTS as $item) {
                ActivityReminderOption::firstOrCreate(['name' => $item['name']], [
                    'minutes' => $item['minutes'],
                ]);
            }

            // Priorities
            foreach (ActivityPriority::DEFAULTS as $item) {
                ActivityPriority::firstOrCreate(['name' => $item['name']], [
                    'color' => $item['color'],
                ]);
            }

            // Default duration
            ActivitySetting::set('default_duration_minutes', '60');

            DB::commit();

            [$types, $reminders, $priorities, $defaultDuration] = $this->fetchAll();

            return $this->success([
                'activity_types' => $types,
                'reminder_options' => $reminders,
                'priorities' => $priorities,
                'default_activity_duration' => $defaultDuration,
            ], 'Default activity settings seeded successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to seed defaults: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  ACTIVITY TYPES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/activity-settings/types
     */
    public function indexTypes(): JsonResponse
    {
        try {
            $types = ActivityType::orderBy('id')->get();
            return $this->success($types, 'Activity types retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve activity types: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/activity-settings/types
     */
    public function storeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:activity_types,name',
        ]);

        try {
            $type = ActivityType::create($validated);
            return $this->success($type, 'Activity type created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create activity type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/activity-settings/types/{id}
     */
    public function showType($id): JsonResponse
    {
        $type = ActivityType::find($id);
        if (!$type) {
            return $this->error('Activity type not found', 404);
        }
        return $this->success($type, 'Activity type retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/activity-settings/types/{id}
     */
    public function updateType(Request $request, $id): JsonResponse
    {
        $type = ActivityType::find($id);
        if (!$type) {
            return $this->error('Activity type not found', 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:activity_types,name,' . $id,
        ]);

        try {
            $type->update($validated);
            return $this->success($type->fresh(), 'Activity type updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update activity type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/activity-settings/types/{id}
     */
    public function destroyType($id): JsonResponse
    {
        $type = ActivityType::find($id);
        if (!$type) {
            return $this->error('Activity type not found', 404);
        }

        try {
            $type->delete();
            return $this->success(null, 'Activity type deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete activity type: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  REMINDER OPTIONS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/activity-settings/reminders
     */
    public function indexReminders(): JsonResponse
    {
        try {
            $reminders = ActivityReminderOption::orderBy('minutes')->orderBy('id')->get();
            return $this->success($reminders, 'Reminder options retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve reminder options: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/activity-settings/reminders
     */
    public function storeReminder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:activity_reminder_options,name',
            'minutes' => 'required|integer|min:0',
        ]);

        try {
            $reminder = ActivityReminderOption::create($validated);
            return $this->success($reminder, 'Reminder option created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create reminder option: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/activity-settings/reminders/{id}
     */
    public function showReminder($id): JsonResponse
    {
        $reminder = ActivityReminderOption::find($id);
        if (!$reminder) {
            return $this->error('Reminder option not found', 404);
        }
        return $this->success($reminder, 'Reminder option retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/activity-settings/reminders/{id}
     */
    public function updateReminder(Request $request, $id): JsonResponse
    {
        $reminder = ActivityReminderOption::find($id);
        if (!$reminder) {
            return $this->error('Reminder option not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:activity_reminder_options,name,' . $id,
            'minutes' => 'sometimes|required|integer|min:0',
        ]);

        try {
            $reminder->update($validated);
            return $this->success($reminder->fresh(), 'Reminder option updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update reminder option: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/activity-settings/reminders/{id}
     */
    public function destroyReminder($id): JsonResponse
    {
        $reminder = ActivityReminderOption::find($id);
        if (!$reminder) {
            return $this->error('Reminder option not found', 404);
        }

        try {
            $reminder->delete();
            return $this->success(null, 'Reminder option deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete reminder option: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIORITY VALUES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/activity-settings/priorities
     */
    public function indexPriorities(): JsonResponse
    {
        try {
            $priorities = ActivityPriority::orderBy('id')->get();
            return $this->success($priorities, 'Activity priorities retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve activity priorities: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/activity-settings/priorities
     */
    public function storePriority(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:activity_priorities,name',
            'color' => 'nullable|string|max:30',
        ]);

        try {
            $validated['color'] = $validated['color'] ?? '#10B981';
            $priority = ActivityPriority::create($validated);
            return $this->success($priority, 'Activity priority created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create activity priority: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/activity-settings/priorities/{id}
     */
    public function showPriority($id): JsonResponse
    {
        $priority = ActivityPriority::find($id);
        if (!$priority) {
            return $this->error('Activity priority not found', 404);
        }
        return $this->success($priority, 'Activity priority retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/activity-settings/priorities/{id}
     */
    public function updatePriority(Request $request, $id): JsonResponse
    {
        $priority = ActivityPriority::find($id);
        if (!$priority) {
            return $this->error('Activity priority not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:activity_priorities,name,' . $id,
            'color' => 'nullable|string|max:30',
        ]);

        try {
            $priority->update($validated);
            return $this->success($priority->fresh(), 'Activity priority updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update activity priority: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/activity-settings/priorities/{id}
     */
    public function destroyPriority($id): JsonResponse
    {
        $priority = ActivityPriority::find($id);
        if (!$priority) {
            return $this->error('Activity priority not found', 404);
        }

        try {
            $priority->delete();
            return $this->success(null, 'Activity priority deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete activity priority: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  DEFAULT DURATION (SETTING)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/activity-settings/duration
     */
    public function getDuration(): JsonResponse
    {
        $minutes = (int) ActivitySetting::get('default_duration_minutes', '60');

        return $this->success([
            'default_duration_minutes' => $minutes,
        ], 'Default activity duration retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/activity-settings/duration
     */
    public function updateDuration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'default_duration_minutes' => 'required|integer|min:1|max:1440',
        ]);

        try {
            ActivitySetting::set('default_duration_minutes', (string) $validated['default_duration_minutes']);

            return $this->success([
                'default_duration_minutes' => (int) $validated['default_duration_minutes'],
            ], 'Default activity duration updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update default activity duration: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchAll(): array
    {
        $types = ActivityType::orderBy('id')->get();
        $reminders = ActivityReminderOption::orderBy('minutes')->orderBy('id')->get();
        $priorities = ActivityPriority::orderBy('id')->get();
        $defaultDuration = 60;
        try {
            $val = ActivitySetting::get('default_duration_minutes', '60');
            $defaultDuration = $val !== null ? (int) $val : 60;
        } catch (\Throwable $e) {
            $defaultDuration = 60;
        }

        return [$types, $reminders, $priorities, $defaultDuration];
    }

    private function defaultTypes(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name], ActivityType::DEFAULTS, array_keys(ActivityType::DEFAULTS));
    }

    private function defaultReminders(): array
    {
        return array_map(fn($item, $i) => array_merge($item, ['id' => $i + 1]), ActivityReminderOption::DEFAULTS, array_keys(ActivityReminderOption::DEFAULTS));
    }

    private function defaultPriorities(): array
    {
        return array_map(fn($item, $i) => array_merge($item, ['id' => $i + 1]), ActivityPriority::DEFAULTS, array_keys(ActivityPriority::DEFAULTS));
    }
}
