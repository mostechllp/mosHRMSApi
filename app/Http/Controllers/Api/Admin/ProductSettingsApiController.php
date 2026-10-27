<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\ProductCategory;
use App\Models\ProductCurrency;
use App\Models\ProductTaxRate;
use App\Models\ProductUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Product Settings API Controller
 *
 * Manages the four product configuration panels:
 *   - Product Categories (Human Resources, Sales, Enterprise, Marketing, Subscription, Support, Hardware, Other)
 *   - Units              (Unit, License, Project, User/Month, Month, Year, Hour, Day)
 *   - Currencies         (INR [Default], USD, AED, GBP)
 *   - Tax Settings       (GST 18% [Default], GST 12%, GST 5%, Exempt [0%])
 *
 * All endpoints are prefixed with /api/admin/crm/product-settings/
 */
class ProductSettingsApiController extends ApiController
{
    public const DEFAULT_CATEGORIES = [
        'Human Resources',
        'Sales',
        'Enterprise',
        'Marketing',
        'Subscription',
        'Support',
        'Hardware',
        'Other',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    //  OVERVIEW / BULK GET & SEED
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return all product setting groups in one call.
     * GET /api/admin/crm/product-settings
     */
    public function index(): JsonResponse
    {
        [$categories, $units, $currencies, $taxes] = $this->fetchAll();

        return $this->success([
            'categories' => $categories,
            'units' => $units,
            'currencies' => $currencies,
            'taxes' => $taxes,
        ], 'Product settings retrieved successfully');
    }

    /**
     * Seed default values for all four groups (only inserts missing ones).
     * POST /api/admin/crm/product-settings/seed-defaults
     */
    public function seedDefaults(): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Categories
            foreach (self::DEFAULT_CATEGORIES as $name) {
                ProductCategory::firstOrCreate(['name' => $name], [
                    'name' => $name,
                    'status' => ProductCategory::STATUS_ACTIVE,
                ]);
            }

            // Units
            foreach (ProductUnit::DEFAULTS as $name) {
                ProductUnit::firstOrCreate(['name' => $name]);
            }

            // Currencies
            foreach (ProductCurrency::DEFAULTS as $item) {
                ProductCurrency::firstOrCreate(['code' => $item['code']], [
                    'symbol' => $item['symbol'],
                    'name' => $item['name'],
                    'is_default' => $item['is_default'],
                ]);
            }

            // Taxes
            foreach (ProductTaxRate::DEFAULTS as $item) {
                ProductTaxRate::firstOrCreate(['name' => $item['name']], [
                    'rate' => $item['rate'],
                    'is_default' => $item['is_default'],
                ]);
            }

            DB::commit();

            [$categories, $units, $currencies, $taxes] = $this->fetchAll();

            return $this->success([
                'categories' => $categories,
                'units' => $units,
                'currencies' => $currencies,
                'taxes' => $taxes,
            ], 'Default product settings seeded successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to seed defaults: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRODUCT CATEGORIES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/product-settings/categories
     */
    public function indexCategories(): JsonResponse
    {
        try {
            $categories = ProductCategory::orderBy('id')->get();
            return $this->success($categories, 'Product categories retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve product categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/product-settings/categories
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:product_categories,name',
            'description' => 'nullable|string|max:500',
            'status' => ['nullable', 'string', Rule::in(ProductCategory::STATUSES)],
        ]);

        try {
            $validated['status'] = $validated['status'] ?? ProductCategory::STATUS_ACTIVE;
            $category = ProductCategory::create($validated);
            return $this->success($category, 'Product category created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create product category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/product-settings/categories/{id}
     */
    public function showCategory($id): JsonResponse
    {
        $category = ProductCategory::find($id);
        if (!$category) {
            return $this->error('Product category not found', 404);
        }
        return $this->success($category, 'Product category retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/product-settings/categories/{id}
     */
    public function updateCategory(Request $request, $id): JsonResponse
    {
        $category = ProductCategory::find($id);
        if (!$category) {
            return $this->error('Product category not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:product_categories,name,' . $id,
            'description' => 'nullable|string|max:500',
            'status' => ['nullable', 'string', Rule::in(ProductCategory::STATUSES)],
        ]);

        try {
            $category->update($validated);
            return $this->success($category->fresh(), 'Product category updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update product category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/product-settings/categories/{id}
     */
    public function destroyCategory($id): JsonResponse
    {
        $category = ProductCategory::find($id);
        if (!$category) {
            return $this->error('Product category not found', 404);
        }

        try {
            $category->delete();
            return $this->success(null, 'Product category deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete product category: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  UNITS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/product-settings/units
     */
    public function indexUnits(): JsonResponse
    {
        try {
            $units = ProductUnit::orderBy('id')->get();
            return $this->success($units, 'Units retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve units: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/product-settings/units
     */
    public function storeUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:product_units,name',
        ]);

        try {
            $unit = ProductUnit::create($validated);
            return $this->success($unit, 'Unit created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create unit: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/product-settings/units/{id}
     */
    public function showUnit($id): JsonResponse
    {
        $unit = ProductUnit::find($id);
        if (!$unit) {
            return $this->error('Unit not found', 404);
        }
        return $this->success($unit, 'Unit retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/product-settings/units/{id}
     */
    public function updateUnit(Request $request, $id): JsonResponse
    {
        $unit = ProductUnit::find($id);
        if (!$unit) {
            return $this->error('Unit not found', 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:product_units,name,' . $id,
        ]);

        try {
            $unit->update($validated);
            return $this->success($unit->fresh(), 'Unit updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update unit: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/product-settings/units/{id}
     */
    public function destroyUnit($id): JsonResponse
    {
        $unit = ProductUnit::find($id);
        if (!$unit) {
            return $this->error('Unit not found', 404);
        }

        try {
            $unit->delete();
            return $this->success(null, 'Unit deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete unit: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  CURRENCIES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/product-settings/currencies
     */
    public function indexCurrencies(): JsonResponse
    {
        try {
            $currencies = ProductCurrency::orderBy('id')->get();
            
            return $this->success($currencies, 'Currencies retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve currencies: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/product-settings/currencies
     */
    public function storeCurrency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:product_currencies,code',
            'symbol' => 'nullable|string|max:10',
            'name' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            $currency = DB::transaction(function () use ($validated) {
                if (!empty($validated['is_default'])) {
                    ProductCurrency::where('is_default', true)->update(['is_default' => false]);
                }
                return ProductCurrency::create($validated);
            });

            return $this->success($currency, 'Currency created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create currency: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/product-settings/currencies/{id}
     */
    public function showCurrency($id): JsonResponse
    {
        $currency = ProductCurrency::find($id);
        if (!$currency) {
            return $this->error('Currency not found', 404);
        }
        return $this->success($currency, 'Currency retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/product-settings/currencies/{id}
     */
    public function updateCurrency(Request $request, $id): JsonResponse
    {
        $currency = ProductCurrency::find($id);
        if (!$currency) {
            return $this->error('Currency not found', 404);
        }

        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:10|unique:product_currencies,code,' . $id,
            'symbol' => 'nullable|string|max:10',
            'name' => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            DB::transaction(function () use ($currency, $validated) {
                if (!empty($validated['is_default'])) {
                    ProductCurrency::where('id', '!=', $currency->id)->update(['is_default' => false]);
                }
                $currency->update($validated);
            });

            return $this->success($currency->fresh(), 'Currency updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update currency: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/admin/crm/product-settings/currencies/{id}/set-default
     */
    public function setDefaultCurrency($id): JsonResponse
    {
        $currency = ProductCurrency::find($id);
        if (!$currency) {
            return $this->error('Currency not found', 404);
        }

        try {
            DB::transaction(function () use ($currency) {
                ProductCurrency::where('id', '!=', $currency->id)->update(['is_default' => false]);
                $currency->update(['is_default' => true]);
            });

            return $this->success($currency->fresh(), 'Default currency updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to set default currency: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/product-settings/currencies/{id}
     */
    public function destroyCurrency($id): JsonResponse
    {
        $currency = ProductCurrency::find($id);
        if (!$currency) {
            return $this->error('Currency not found', 404);
        }

        try {
            $currency->delete();
            return $this->success(null, 'Currency deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete currency: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  TAX SETTINGS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/crm/product-settings/taxes
     */
    public function indexTaxes(): JsonResponse
    {
        try {
            $taxes = ProductTaxRate::orderBy('rate', 'desc')->orderBy('id')->get();
            
            return $this->success($taxes, 'Tax rates retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to retrieve tax rates: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/admin/crm/product-settings/taxes
     */
    public function storeTax(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:product_tax_rates,name',
            'rate' => 'required|numeric|between:0,100',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            $tax = DB::transaction(function () use ($validated) {
                if (!empty($validated['is_default'])) {
                    ProductTaxRate::where('is_default', true)->update(['is_default' => false]);
                }
                return ProductTaxRate::create($validated);
            });

            return $this->success($tax, 'Tax rate created successfully', 201);
        } catch (\Throwable $e) {
            return $this->error('Failed to create tax rate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/admin/crm/product-settings/taxes/{id}
     */
    public function showTax($id): JsonResponse
    {
        $tax = ProductTaxRate::find($id);
        if (!$tax) {
            return $this->error('Tax rate not found', 404);
        }
        return $this->success($tax, 'Tax rate retrieved successfully');
    }

    /**
     * PUT /api/admin/crm/product-settings/taxes/{id}
     */
    public function updateTax(Request $request, $id): JsonResponse
    {
        $tax = ProductTaxRate::find($id);
        if (!$tax) {
            return $this->error('Tax rate not found', 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100|unique:product_tax_rates,name,' . $id,
            'rate' => 'sometimes|required|numeric|between:0,100',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            DB::transaction(function () use ($tax, $validated) {
                if (!empty($validated['is_default'])) {
                    ProductTaxRate::where('id', '!=', $tax->id)->update(['is_default' => false]);
                }
                $tax->update($validated);
            });

            return $this->success($tax->fresh(), 'Tax rate updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update tax rate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PATCH /api/admin/crm/product-settings/taxes/{id}/set-default
     */
    public function setDefaultTax($id): JsonResponse
    {
        $tax = ProductTaxRate::find($id);
        if (!$tax) {
            return $this->error('Tax rate not found', 404);
        }

        try {
            DB::transaction(function () use ($tax) {
                ProductTaxRate::where('id', '!=', $tax->id)->update(['is_default' => false]);
                $tax->update(['is_default' => true]);
            });

            return $this->success($tax->fresh(), 'Default tax rate updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to set default tax rate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/admin/crm/product-settings/taxes/{id}
     */
    public function destroyTax($id): JsonResponse
    {
        $tax = ProductTaxRate::find($id);
        if (!$tax) {
            return $this->error('Tax rate not found', 404);
        }

        try {
            $tax->delete();
            return $this->success(null, 'Tax rate deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete tax rate: ' . $e->getMessage(), 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  PRIVATE HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private function fetchAll(): array
    {
            $categories = ProductCategory::orderBy('id')->get();
            $units = ProductUnit::orderBy('id')->get();
            $currencies = ProductCurrency::orderBy('id')->get();
            
            $taxes = ProductTaxRate::orderBy('rate', 'desc')->orderBy('id')->get();
            

        return [$categories, $units, $currencies, $taxes];
    }

    private function defaultCategories(): array
    {
        return array_map(fn($name, $i) => [
            'id' => $i + 1,
            'name' => $name,
            'status' => 'Active',
        ], self::DEFAULT_CATEGORIES, array_keys(self::DEFAULT_CATEGORIES));
    }

    private function defaultUnits(): array
    {
        return array_map(fn($name, $i) => [
            'id' => $i + 1,
            'name' => $name,
        ], ProductUnit::DEFAULTS, array_keys(ProductUnit::DEFAULTS));
    }

    private function defaultCurrencies(): array
    {
        return array_map(fn($item, $i) => array_merge($item, ['id' => $i + 1]), ProductCurrency::DEFAULTS, array_keys(ProductCurrency::DEFAULTS));
    }

    private function defaultTaxes(): array
    {
        return array_map(fn($item, $i) => array_merge($item, ['id' => $i + 1]), ProductTaxRate::DEFAULTS, array_keys(ProductTaxRate::DEFAULTS));
    }
}
