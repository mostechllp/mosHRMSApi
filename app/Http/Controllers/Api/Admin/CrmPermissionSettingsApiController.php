<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\CrmPermission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CRM Permission Settings API Controller
 *
 * Manages the per-role, per-action CRM permission matrix shown in
 * the CRM Permissions settings panel.
 *
 * All endpoints: /api/admin/crm/crm-permissions/
 *
 * Permission matrix structure:
 *   - Rows    : permission_key  (view_all_customers, create_leads, …)
 *   - Columns : roles           (Super Admin, Admin, Sales Manager, Salesperson, Support)
 */
class CrmPermissionSettingsApiController extends ApiController
{
    // ──────────────────────────────────────────────────────────────────────────
    //  OVERVIEW
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return the full permission matrix grouped by permission_key.
     *
     * GET /api/admin/crm/crm-permissions
     *
     * Response shape:
     * {
     *   "permissions": {
     *     "view_all_customers": [
     *       { "role_id": 1, "role_name": "Super Admin", "is_allowed": true },
     *       ...
     *     ],
     *     ...
     *   },
     *   "permission_keys": [...],
     *   "roles": [{ "id": 1, "name": "Super Admin" }, ...]
     * }
     */
    public function index(): JsonResponse
    {
        try {
            $roles = Role::orderBy('id')->get(['id', 'name']);
            $matrix = $this->buildMatrix($roles);

            return $this->success([
                'permissions' => $matrix,
                'permission_keys' => CrmPermission::PERMISSION_KEYS,
                'roles' => $roles,
            ], 'CRM permissions retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve CRM permissions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Return permissions for a specific role.
     *
     * GET /api/admin/crm/crm-permissions/roles/{role_id}
     *
     * Response shape:
     * {
     *   "role": { "id": 2, "name": "Admin" },
     *   "permissions": {
     *     "view_all_customers": true,
     *     "create_leads": true,
     *     ...
     *   }
     * }
     */
    public function showByRole(int $roleId): JsonResponse
    {
        $role = Role::find($roleId);
        if (!$role) {
            return $this->error('Role not found', 404);
        }

        try {
            $permissions = $this->permissionsForRole($roleId);

            return $this->success([
                'role' => $role->only(['id', 'name']),
                'permissions' => $permissions,
            ], 'CRM permissions for role retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve role CRM permissions: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  UPDATE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Bulk-update the entire permission matrix in one request.
     *
     * PUT /api/admin/crm/crm-permissions
     *
     * Request body:
     * {
     *   "permissions": [
     *     { "role_id": 2, "permission_key": "view_all_customers", "is_allowed": true },
     *     ...
     *   ]
     * }
     */
    public function updateBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*.role_id' => 'required|integer|exists:roles,id',
            'permissions.*.permission_key' => ['required', 'string', Rule::in(CrmPermission::PERMISSION_KEYS)],
            'permissions.*.is_allowed' => 'required|boolean',
        ]);

        try {
            DB::beginTransaction();

            foreach ($validated['permissions'] as $item) {
                // Super Admin (role_id = 1) always has full access – skip silently
                if ($item['role_id'] === 1) {
                    continue;
                }

                CrmPermission::updateOrCreate(
                    [
                        'role_id' => $item['role_id'],
                        'permission_key' => $item['permission_key'],
                    ],
                    ['is_allowed' => $item['is_allowed']]
                );
            }

            DB::commit();

            $roles = Role::orderBy('id')->get(['id', 'name']);
            $matrix = $this->buildMatrix($roles);

            return $this->success([
                'permissions' => $matrix,
                'permission_keys' => CrmPermission::PERMISSION_KEYS,
                'roles' => $roles,
            ], 'CRM permissions updated successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to update CRM permissions: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update permissions for a single role.
     *
     * PUT /api/admin/crm/crm-permissions/roles/{role_id}
     *
     * Request body:
     * {
     *   "permissions": {
     *     "view_all_customers": true,
     *     "create_leads": false,
     *     ...
     *   }
     * }
     */
    public function updateByRole(Request $request, int $roleId): JsonResponse
    {
        $role = Role::find($roleId);
        if (!$role) {
            return $this->error('Role not found', 404);
        }

        // Super Admin cannot be restricted
        if ($roleId === 1) {
            return $this->error('Super Admin permissions cannot be modified', 422);
        }

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'required|boolean',
        ]);

        // Validate that all keys are recognised
        $unknownKeys = array_diff(
            array_keys($validated['permissions']),
            CrmPermission::PERMISSION_KEYS
        );
        if (!empty($unknownKeys)) {
            return $this->error(
                'Unknown permission keys: ' . implode(', ', $unknownKeys),
                422
            );
        }

        try {
            DB::beginTransaction();

            foreach ($validated['permissions'] as $key => $isAllowed) {
                CrmPermission::updateOrCreate(
                    ['role_id' => $roleId, 'permission_key' => $key],
                    ['is_allowed' => $isAllowed]
                );
            }

            DB::commit();

            $permissions = $this->permissionsForRole($roleId);

            return $this->success([
                'role' => $role->only(['id', 'name']),
                'permissions' => $permissions,
            ], 'CRM permissions for role updated successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to update role CRM permissions: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  SEED DEFAULTS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Seed default permissions (only inserts missing rows, never overwrites).
     *
     * POST /api/admin/crm/crm-permissions/seed-defaults
     */
    public function seedDefaults(): JsonResponse
    {
        try {
            DB::beginTransaction();

            foreach (CrmPermission::DEFAULTS as $roleId => $perms) {
                if (!Role::where('id', $roleId)->exists()) {
                    continue;
                }

                foreach ($perms as $key => $isAllowed) {
                    CrmPermission::firstOrCreate(
                        ['role_id' => $roleId, 'permission_key' => $key],
                        ['is_allowed' => $isAllowed]
                    );
                }
            }

            DB::commit();

            $roles = Role::orderBy('id')->get(['id', 'name']);
            $matrix = $this->buildMatrix($roles);

            return $this->success([
                'permissions' => $matrix,
                'permission_keys' => CrmPermission::PERMISSION_KEYS,
                'roles' => $roles,
            ], 'Default CRM permissions seeded successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to seed default CRM permissions: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build the full matrix: permission_key => [ { role_id, role_name, is_allowed } ]
     */
    private function buildMatrix($roles): array
    {
        // Load all rows in one query
        $rows = CrmPermission::all()->keyBy(fn($r) => $r->role_id . '_' . $r->permission_key);

        $matrix = [];

        foreach (CrmPermission::PERMISSION_KEYS as $key) {
            $matrix[$key] = [];

            foreach ($roles as $role) {
                $lookup = $rows->get($role->id . '_' . $key);

                // Super Admin always has all access regardless of DB value
                if ($role->id === 1) {
                    $isAllowed = true;
                } elseif ($lookup) {
                    $isAllowed = $lookup->is_allowed;
                } else {
                    // Fall back to constant defaults if no DB row exists
                    $isAllowed = CrmPermission::DEFAULTS[$role->id][$key] ?? false;
                }

                $matrix[$key][] = [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'is_allowed' => $isAllowed,
                ];
            }
        }

        return $matrix;
    }

    /**
     * Return a flat key=>bool map for a single role.
     */
    private function permissionsForRole(int $roleId): array
    {
        $rows = CrmPermission::where('role_id', $roleId)
            ->pluck('is_allowed', 'permission_key')
            ->toArray();

        $result = [];
        foreach (CrmPermission::PERMISSION_KEYS as $key) {
            if ($roleId === 1) {
                $result[$key] = true;
            } elseif (array_key_exists($key, $rows)) {
                $result[$key] = (bool) $rows[$key];
            } else {
                $result[$key] = CrmPermission::DEFAULTS[$roleId][$key] ?? false;
            }
        }

        return $result;
    }
}
