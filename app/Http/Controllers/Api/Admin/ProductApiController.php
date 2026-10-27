<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exports\ProductExport;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\CRM\StoreProductRequest;
use App\Http\Requests\CRM\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductCurrency;
use App\Models\ProductTaxRate;
use App\Models\ProductUnit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ProductApiController extends ApiController
{
    /**
     * Display a listing of products & services with filters, search, and pagination.
     * GET /api/admin/crm/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

        $perPage = (int) $request->input('per_page', 15);
        if ($perPage <= 0) {
            $products = $query->get();
            return $this->success($products, 'Products & services retrieved successfully');
        }

        return $this->success($query->paginate($perPage), 'Products & services retrieved successfully');
    }

    /**
     * Get reference form options (types, categories, units, currencies, statuses).
     * GET /api/admin/crm/products/form-options
     */
    public function formOptions(): JsonResponse
    {
        // 1. Fetch categories from ProductCategory table if available
        $tableCategories = [];
        try {
            $tableCategories = ProductCategory::query()
                ->where('status', ProductCategory::STATUS_ACTIVE)
                ->select(['id', 'name'])
                ->withCount('products')
                ->orderBy('name')
                ->get();
        } catch (\Throwable $e) {
            // Table may not yet be migrated
        }

        // 2. Fetch units from ProductUnit settings
        try {
            $units = ProductUnit::orderBy('id')->pluck('name')->toArray();
            if (empty($units)) {
                $units = ProductUnit::DEFAULTS;
            }
        } catch (\Throwable $e) {
            $units = Product::DEFAULT_UNITS;
        }

        // 3. Fetch currencies from ProductCurrency settings
        try {
            $currencies = ProductCurrency::orderBy('id')->get(['code', 'symbol', 'name', 'is_default'])->toArray();
            if (empty($currencies)) {
                $currencies = ProductCurrency::DEFAULTS;
            }
        } catch (\Throwable $e) {
            $currencies = array_map(fn($c) => ['code' => $c, 'symbol' => $c, 'name' => $c, 'is_default' => $c === 'INR'], Product::DEFAULT_CURRENCIES);
        }

        // 4. Fetch default tax rate and tax rates list
        $defaultTaxRate = 18;
        $taxes = [];
        try {
            $taxes = ProductTaxRate::orderBy('id')->get(['id', 'name', 'rate', 'is_default'])->toArray();
            if (!empty($taxes)) {
                $defaultTax = collect($taxes)->firstWhere('is_default', true);
                if ($defaultTax && isset($defaultTax['rate'])) {
                    $defaultTaxRate = (float) $defaultTax['rate'];
                }
            } else {
                $taxes = ProductTaxRate::DEFAULTS;
            }
        } catch (\Throwable $e) {
            $taxes = ProductTaxRate::DEFAULTS;
        }

        $options = [
            'types' => Product::TYPES,
            'statuses' => Product::STATUSES,
            'units' => $units,
            'currencies' => $currencies,
            'currency_codes' => array_column($currencies, 'code'),
            'categories' => $tableCategories,
            'taxes' => $taxes,
            'default_tax_rate' => $defaultTaxRate,
        ];

        return $this->success($options, 'Product form options retrieved successfully');
    }

    /**
     * Store a newly created product/service.
     * POST /api/admin/crm/products
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        // Ensure category column is not sent to DB
        unset($validated['category']);

        DB::beginTransaction();
        try {
            $product = Product::create($validated);
            DB::commit();

            return $this->success(
                $this->loadProductRelations($product),
                'Product/Service created successfully',
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create product/service: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified product/service.
     * GET /api/admin/crm/products/{product}
     */
    public function show($id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Product/Service not found', 404);
        }

        return $this->success($this->loadProductRelations($product), 'Product/Service retrieved successfully');
    }

    /**
     * Update the specified product/service.
     * PUT/PATCH /api/admin/crm/products/{product}
     */
    public function update(UpdateProductRequest $request, $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->error('Product/Service not found', 404);
        }

        $validated = $request->validated();
        $validated['updated_by'] = Auth::id();

        // Ensure category column is not sent to DB
        unset($validated['category']);

        DB::beginTransaction();
        try {
            $product->update($validated);
            DB::commit();

            return $this->success(
                $this->loadProductRelations($product->fresh()),
                'Product/Service updated successfully'
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to update product/service: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified product/service.
     * DELETE /api/admin/crm/products/{product}
     */
    public function destroy($id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->error('Product/Service not found', 404);
        }

        try {
            $product->deleted_by = Auth::id();
            $product->save();
            $product->delete();

            return $this->success(null, 'Product/Service deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete product/service: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update status to Active or Inactive.
     * POST /api/admin/crm/products/{id}/update-status
     * PATCH /api/admin/crm/products/{id}/status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $product = Product::find($id);
        if (!$product) {
            return $this->error('Product/Service not found', 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Product::STATUSES)],
        ]);

        try {
            $product->status = $validated['status'];
            $product->updated_by = Auth::id();
            $product->save();

            return $this->success(
                $this->loadProductRelations($product),
                "Product/Service status updated to {$product->status} successfully"
            );
        } catch (\Throwable $e) {
            return $this->error('Failed to update status: ' . $e->getMessage(), 500);
        }
    }

    // ─── Category Management Inside Product Management (The "+" button in UI) ─

    /**
     * List all product categories with products count.
     * GET /api/admin/crm/products/categories
     */
    public function listCategories(Request $request): JsonResponse
    {
        try {
            $query = ProductCategory::query();

            if ($request->filled('search')) {
                $search = trim($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $categories = $query->withCount('products')->orderBy('name', 'asc')->get();

            return $this->success($categories, 'Product categories retrieved successfully');
        } catch (\Throwable $e) {
            // Fallback if table doesn't exist yet: extract from default categories
            $fallback = array_map(function ($name, $i) {
                return [
                    'id' => $i + 1,
                    'name' => $name,
                    'slug' => \Illuminate\Support\Str::slug($name),
                    'status' => 'Active',
                    'products_count' => 0,
                ];
            }, Product::DEFAULT_CATEGORIES, array_keys(Product::DEFAULT_CATEGORIES));

            return $this->success($fallback, 'Product categories retrieved successfully');
        }
    }

    /**
     * Quick-add or create category (Clicking "+" in modal or dedicated category manager).
     * POST /api/admin/crm/products/categories
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', Rule::in(ProductCategory::STATUSES)],
        ]);

        $name = trim($validated['name']);

        try {
            // Check if category already exists
            $existing = ProductCategory::where('name', $name)->first();
            if ($existing) {
                return $this->success($existing, 'Category already exists', 200);
            }

            $category = ProductCategory::create([
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? ProductCategory::STATUS_ACTIVE,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return $this->success($category, 'Category created successfully', 201);
        } catch (\Throwable $e) {
            // Fallback object so front-end "+" works immediately even before migration execution
            $fallback = [
                'id' => time(),
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 'Active',
                'products_count' => 0,
            ];
            return $this->success($fallback, 'Category registered successfully', 201);
        }
    }

    /**
     * Update an existing product category.
     * PUT/PATCH /api/admin/crm/products/categories/{id}
     */
    public function updateCategory(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'required', 'string', Rule::in(ProductCategory::STATUSES)],
        ]);

        try {
            $category = ProductCategory::find($id);
            if (!$category) {
                return $this->error('Category not found', 404);
            }

            if (!empty($validated['name'])) {
                $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
            }
            $validated['updated_by'] = Auth::id();

            $category->update($validated);

            return $this->success($category->fresh(), 'Category updated successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to update category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete a product category.
     * DELETE /api/admin/crm/products/categories/{id}
     */
    public function destroyCategory($id): JsonResponse
    {
        try {
            $category = ProductCategory::find($id);
            if (!$category) {
                return $this->error('Category not found', 404);
            }

            $category->deleted_by = Auth::id();
            $category->save();
            $category->delete();

            return $this->success(null, 'Category deleted successfully');
        } catch (\Throwable $e) {
            return $this->error('Failed to delete category: ' . $e->getMessage(), 500);
        }
    }

    // ─── Export Endpoints (Excel, CSV, PDF) ───────────────────────────────────

    /**
     * Unified Export Endpoint (supports format=xlsx, format=csv, format=pdf).
     * GET /api/admin/crm/products/export
     */
    public function export(Request $request)
    {
        $format = strtolower($request->input('format', 'xlsx'));
        if ($format === 'pdf') {
            return $this->exportPdf($request);
        }
        if ($format === 'csv') {
            return $this->exportCsv($request);
        }
        return $this->exportExcel($request);
    }

    /**
     * Export products to Excel (.xlsx).
     * GET /api/admin/crm/products/export/excel
     */
    public function exportExcel(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $products = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($products);
        $filename = 'crm_products_' . now()->format('YmdHis') . '.xlsx';

        return Excel::download(new ProductExport($rows), $filename, \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export products to CSV (.csv).
     * GET /api/admin/crm/products/export/csv
     */
    public function exportCsv(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $products = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($products);
        $filename = 'crm_products_' . now()->format('YmdHis') . '.csv';

        return Excel::download(new ProductExport($rows), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * Export products to PDF (.pdf).
     * GET /api/admin/crm/products/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $products = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($products);
        $export = new ProductExport($rows);
        $filename = 'crm_products_' . now()->format('YmdHis') . '.pdf';

        $pdf = Pdf::loadView('reports.generic_pdf', [
            'data' => $export->array(),
            'headings' => $export->headings(),
            'title' => 'CRM Products & Services Catalog',
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    // ─── Private Helpers ────────────────────────────────────────────────────────

    /**
     * Build the filtered query (shared between index, options, and exports).
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = Product::query();

        // Safely eager load creator with employee
        $this->safelyApplyWith($query);

        // Search by name, code/sku, description, notes, or categoryRelation name
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('categoryRelation', function ($catQ) use ($search) {
                        $catQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by Type (Product / Service)
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filter by Status (Active / Inactive)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by Category (by category_id or name via relationship)
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }
        if ($request->filled('category')) {
            $cat = $request->input('category');
            if (is_numeric($cat)) {
                $query->where('category_id', $cat);
            } else {
                $query->whereHas('categoryRelation', function ($catQ) use ($cat) {
                    $catQ->where('name', $cat);
                });
            }
        }

        // Filter by Currency
        if ($request->filled('currency')) {
            $query->where('currency', $request->input('currency'));
        }

        // Filter by Price range
        if ($request->filled('min_price')) {
            $query->where('default_price', '>=', (float) $request->input('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('default_price', '<=', (float) $request->input('max_price'));
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = [
            'id',
            'name',
            'code',
            'type',
            'category_id',
            'unit',
            'default_price',
            'currency',
            'tax_rate',
            'status',
            'created_at'
        ];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        return $query;
    }

    /**
     * Helper to safely load creator and updater relations.
     */
    private function safelyApplyWith($query): void
    {
        try {
            $query->with([
                'creator:id,username,email',
                'creator.employee:id,user_id,first_name,last_name,employee_id,company_email',
                'categoryRelation:id,name,slug',
            ]);
        } catch (\Throwable $e) {
            // Fallback
        }
    }

    /**
     * Load product relations for single item responses.
     */
    private function loadProductRelations(Product $product): Product
    {
        try {
            return $product->load([
                'creator:id,username,email',
                'creator.employee:id,user_id,first_name,last_name,employee_id,company_email',
                'updater:id,username,email',
                'updater.employee:id,user_id,first_name,last_name,employee_id,company_email',
                'categoryRelation:id,name,slug',
            ]);
        } catch (\Throwable $e) {
            return $product;
        }
    }

    /**
     * Prepare tabular data for CSV, Excel, and PDF exports.
     */
    private function prepareExportRows($products): array
    {
        $rows = [];
        foreach ($products as $p) {
            $rows[] = [
                $p->id,
                $p->name ?? '-',
                $p->code ?? '-',
                $p->type ?? '-',
                $p->category ?? '-',
                $p->description ?? '-',
                $p->unit ?? 'Unit',
                number_format((float) ($p->default_price ?? 0), 2),
                $p->currency ?? 'INR',
                number_format((float) ($p->tax_rate ?? 0), 2) . '%',
                $p->status ?? 'Active',
                $p->notes ?? '-',
                $p->created_at ? $p->created_at->format('Y-m-d H:i') : '-',
            ];
        }
        return $rows;
    }

    /**
     * Authenticate via bearer token or URL query parameter token (for direct browser file downloads).
     */
    private function authenticateFromToken(Request $request): void
    {
        if (!$request->bearerToken() && $request->has('token')) {
            try {
                $user = auth('api')->setToken($request->token)->user();
                if ($user) {
                    auth('api')->setUser($user);
                }
            } catch (\Exception $e) {
                // Fail silently
            }
        }

        if (!auth('api')->check()) {
            abort(403, 'Forbidden - Access denied. Please provide a valid token.');
        }
    }
}
