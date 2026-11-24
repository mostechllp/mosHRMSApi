<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\CustomerCategory;
use App\Models\CustomerIndustry;
use App\Models\CustomerStatus;
use App\Models\CustomerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer Settings API Controller
 *
 * Manages the four customer configuration panels:
 *   - Customer Categories (Prospect, Customer, Partner)
 *   - Industries          (IT Services, Manufacturing, Fintech, etc.)
 *   - Customer Statuses   (Active, Inactive)
 *   - Customer Types      (Individual, Company)
 *
 * All endpoints are prefixed with /api/admin/crm/customer-settings/
 */
class CustomerSettingsApiController extends ApiController
{
    // ──────────────────────────────────────────────────────────────────────────
    //  OVERVIEW / BULK GET & SEED
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return all four customer setting groups in one call.
     * GET /api/admin/crm/customer-settings
     */
    public function index(): JsonResponse
    {
        [$categories, $industries, $statuses, $types] = $this->fetchAll();

        return $this->success([
            'categories' => $categories,
            'industries' => $industries,
            'statuses' => $statuses,
            'types' => $types,
        ], 'Customer settings retrieved successfully');
    }

    /**
     * Seed default values for all four groups (only inserts missing ones).
     * POST /api/admin/crm/customer-settings/seed-defaults
     */
    public function seedDefaults(): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Categories
            foreach (CustomerCategory::DEFAULTS as $name) {
                CustomerCategory::firstOrCreate(['name' => $name]);
            }

            // Industries
            foreach (CustomerIndustry::DEFAULTS as $name) {
                CustomerIndustry::firstOrCreate(['name' => $name]);
            }

            // Statuses
            foreach (CustomerStatus::DEFAULTS as $name) {
                CustomerStatus::firstOrCreate(['name' => $name]);
            }

            // Types
            foreach (CustomerType::DEFAULTS as $name) {
                CustomerType::firstOrCreate(['name' => $name]);
            }

            DB::commit();

            [$categories, $industries, $statuses, $types] = $this->fetchAll();

            return $this->success([
                'categories' => $categories,
                'industries' => $industries,
                'statuses' => $statuses,
                'types' => $types,
            ], 'Default customer settings seeded successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to seed defaults: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  CUSTOMER CATEGORIES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/customer-settings/categories
     */
    public function indexCategories(): JsonResponse
    {
        try {
            $categories = CustomerCategory::orderBy('id')->get();
            return $this->success($categories, 'Customer categories retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve customer categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/customer-settings/categories
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:customer_categories,name',
        ]);

        try {
            $category = CustomerCategory::create($validated);
            return $this->success($category, 'Customer category created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create customer category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/customer-settings/categories/{id}
     */
    public function showCategory($id): JsonResponse
    {
        $category = CustomerCategory::find($id);
        if (!$category) {
            return $this->error('Customer category not found', 404);
        }
        return $this->success($category, 'Customer category retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/customer-settings/categories/{id}
     */
    public function updateCategory(Request $request, $id): JsonResponse
    {
        $category = CustomerCategory::find($id);
        if (!$category) {
            return $this->error('Customer category not found', 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:customer_categories,name,' . $id,
        ]);

        try {
            $category->update($validated);
            return $this->success($category->fresh(), 'Customer category updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update customer category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/customer-settings/categories/{id}
     */
    public function destroyCategory($id): JsonResponse
    {
        $category = CustomerCategory::find($id);
        if (!$category) {
            return $this->error('Customer category not found', 404);
        }

        try {
            $category->delete();
            return $this->success(null, 'Customer category deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete customer category: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  CUSTOMER INDUSTRIES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/customer-settings/industries
     */
    public function indexIndustries(): JsonResponse
    {
        try {
            $industries = CustomerIndustry::orderBy('id')->get();
            return $this->success($industries, 'Customer industries retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve customer industries: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/customer-settings/industries
     */
    public function storeIndustry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:customer_industries,name',
        ]);

        try {
            $industry = CustomerIndustry::create($validated);
            return $this->success($industry, 'Customer industry created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create customer industry: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/customer-settings/industries/{id}
     */
    public function showIndustry($id): JsonResponse
    {
        $industry = CustomerIndustry::find($id);
        if (!$industry) {
            return $this->error('Customer industry not found', 404);
        }
        return $this->success($industry, 'Customer industry retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/customer-settings/industries/{id}
     */
    public function updateIndustry(Request $request, $id): JsonResponse
    {
        $industry = CustomerIndustry::find($id);
        if (!$industry) {
            return $this->error('Customer industry not found', 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150|unique:customer_industries,name,' . $id,
        ]);

        try {
            $industry->update($validated);
            return $this->success($industry->fresh(), 'Customer industry updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update customer industry: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/customer-settings/industries/{id}
     */
    public function destroyIndustry($id): JsonResponse
    {
        $industry = CustomerIndustry::find($id);
        if (!$industry) {
            return $this->error('Customer industry not found', 404);
        }

        try {
            $industry->delete();
            return $this->success(null, 'Customer industry deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete customer industry: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  CUSTOMER STATUSES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/customer-settings/statuses
     */
    public function indexStatuses(): JsonResponse
    {
        try {
            $statuses = CustomerStatus::orderBy('id')->get();
            return $this->success($statuses, 'Customer statuses retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve customer statuses: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/customer-settings/statuses
     */
    public function storeStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:customer_statuses,name',
        ]);

        try {
            $status = CustomerStatus::create($validated);
            return $this->success($status, 'Customer status created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create customer status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/customer-settings/statuses/{id}
     */
    public function showStatus($id): JsonResponse
    {
        $status = CustomerStatus::find($id);
        if (!$status) {
            return $this->error('Customer status not found', 404);
        }
        return $this->success($status, 'Customer status retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/customer-settings/statuses/{id}
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $status = CustomerStatus::find($id);
        if (!$status) {
            return $this->error('Customer status not found', 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:customer_statuses,name,' . $id,
        ]);

        try {
            $status->update($validated);
            return $this->success($status->fresh(), 'Customer status updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update customer status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/customer-settings/statuses/{id}
     */
    public function destroyStatus($id): JsonResponse
    {
        $status = CustomerStatus::find($id);
        if (!$status) {
            return $this->error('Customer status not found', 404);
        }

        try {
            $status->delete();
            return $this->success(null, 'Customer status deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete customer status: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  CUSTOMER TYPES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/customer-settings/types
     */
    public function indexTypes(): JsonResponse
    {
        try {
            $types = CustomerType::orderBy('id')->get();
            return $this->success($types, 'Customer types retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve customer types: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/customer-settings/types
     */
    public function storeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:customer_types,name',
        ]);

        try {
            $type = CustomerType::create($validated);
            return $this->success($type, 'Customer type created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create customer type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/customer-settings/types/{id}
     */
    public function showType($id): JsonResponse
    {
        $type = CustomerType::find($id);
        if (!$type) {
            return $this->error('Customer type not found', 404);
        }
        return $this->success($type, 'Customer type retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/customer-settings/types/{id}
     */
    public function updateType(Request $request, $id): JsonResponse
    {
        $type = CustomerType::find($id);
        if (!$type) {
            return $this->error('Customer type not found', 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:customer_types,name,' . $id,
        ]);

        try {
            $type->update($validated);
            return $this->success($type->fresh(), 'Customer type updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update customer type: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/customer-settings/types/{id}
     */
    public function destroyType($id): JsonResponse
    {
        $type = CustomerType::find($id);
        if (!$type) {
            return $this->error('Customer type not found', 404);
        }

        try {
            $type->delete();
            return $this->success(null, 'Customer type deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete customer type: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchAll(): array
    {
        $categories = CustomerCategory::orderBy('id')->get();
        $industries = CustomerIndustry::orderBy('id')->get();
        $statuses = CustomerStatus::orderBy('id')->get();
        $types = CustomerType::orderBy('id')->get();

        return [$categories, $industries, $statuses, $types];
    }

    private function defaultCategories(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name], CustomerCategory::DEFAULTS, array_keys(CustomerCategory::DEFAULTS));
    }

    private function defaultIndustries(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name], CustomerIndustry::DEFAULTS, array_keys(CustomerIndustry::DEFAULTS));
    }

    private function defaultStatuses(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name], CustomerStatus::DEFAULTS, array_keys(CustomerStatus::DEFAULTS));
    }

    private function defaultTypes(): array
    {
        return array_map(fn($name, $i) => ['id' => $i + 1, 'name' => $name], CustomerType::DEFAULTS, array_keys(CustomerType::DEFAULTS));
    }
}
