<?php

namespace App\Http\Controllers\Api\Admin\Accounts;

use App\Exports\AccountsItemExport;
use App\Http\Controllers\Api\ApiController;
use App\Imports\AccountsItemImport;
use App\Models\AccountsItem;
use App\Models\AccountsVendor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ItemApiController extends ApiController
{
    /**
     * Display a listing of items with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

        $perPage = max(1, (int) $request->input('per_page', 15));
        $items = $query->paginate($perPage);

        return $this->success($items);
    }

    /**
     * Dropdown / form options for creating or editing an item.
     * Fetches vendors from database + default accounts and usage units.
     */
    public function formOptions(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $vendors = AccountsVendor::query()
            ->where(function ($q) use ($user) {
                if ($user && $user->company_id) {
                    $q->where('company_id', $user->company_id)->orWhereNull('company_id');
                }
            })
            ->where('status', 'active')
            ->select('id', 'name', 'company_name', 'email', 'phone')
            ->get();

        $usageUnits = [
            'pcs'    => 'Pieces (pcs)',
            'box'    => 'Box',
            'kg'     => 'Kilogram (kg)',
            'm'      => 'Meter (m)',
            'sqm'    => 'Square Meter (sqm)',
            'hrs'    => 'Hours (hrs)',
            'days'   => 'Days',
            'nos'    => 'Numbers (nos)',
            'pkt'    => 'Packet (pkt)',
            'set'    => 'Set',
            'units'  => 'Units',
        ];

        $salesAccounts = [
            'Sales',
            'Discount',
            'General Income',
            'Interest Income',
            'Late Fee Income',
            'Other Charges',
            'Shipping Charge',
        ];

        $purchaseAccounts = [
            'Cost of Goods Sold',
            'Advertising and Marketing',
            'Automobile Expense',
            'Bad Debt',
            'Bank Fees and Charges',
            'Consultant Expense',
            'Contract Assets',
            'Credit Card Charges',
            'Depreciation Expense',
            'IT and Internet Expenses',
            'Janitorial Expense',
            'Lodging',
            'Meals and Entertainment',
            'Office Supplies',
            'Other Expenses',
            'Postage',
            'Printing and Stationery',
            'Rent Expense',
            'Repairs and Maintenance',
            'Salaries and Employee Wages',
            'Telephone Expense',
            'Travel Expense',
        ];

        return $this->success([
            'vendors'           => $vendors,
            'types'             => ['Goods', 'Service'],
            'usage_units'       => $usageUnits,
            'sales_accounts'    => $salesAccounts,
            'purchase_accounts' => $purchaseAccounts,
            'currencies'        => ['INR', 'USD', 'EUR', 'GBP', 'AED', 'SAR', 'JPY', 'RUB', 'CNY'],
        ]);
    }

    /**
     * Store a newly created item.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'                 => 'required|string|max:255',
            'type'                 => 'nullable|in:Goods,Service,goods,service',
            'usage_unit'           => 'nullable|string|max:100',
            'usageUnit'            => 'nullable|string|max:100',
            'selling_price'        => 'required_without:sellingPrice|numeric',
            'sellingPrice'         => 'nullable|numeric',
            'selling_currency'     => 'nullable|string|max:20',
            'sales_account'        => 'nullable|string|max:150',
            'salesAccount'         => 'nullable|string|max:150',
            'sales_description'    => 'nullable|string',
            'salesDescription'     => 'nullable|string',
            'cost_price'           => 'required_without:costPrice|numeric',
            'costPrice'            => 'nullable|numeric',
            'cost_currency'        => 'nullable|string|max:20',
            'purchase_account'     => 'nullable|string|max:150',
            'purchaseAccount'      => 'nullable|string|max:150',
            'preferred_vendor_id'  => 'nullable|numeric',
            'preferredVendorId'    => 'nullable|numeric',
            'preferred_vendor_name'=> 'nullable|string|max:255',
            'preferredVendor'      => 'nullable|string|max:255',
            'purchase_description' => 'nullable|string',
            'purchaseDescription'  => 'nullable|string',
            'status'               => 'nullable|in:active,inactive,Active,Inactive',
        ]);

        $user = auth('api')->user();
        $payload = $this->formatItemPayload($request);
        $payload['company_id'] = $user?->company_id;
        $payload['created_by'] = $user?->id;
        $payload['source']     = 'user';

        $item = AccountsItem::create($payload);

        try {
            $item->load('preferredVendor');
        } catch (\Throwable $e) {}

        return $this->success($item, 'Item created successfully', 201);
    }

    /**
     * Display the specified item.
     */
    public function show($id): JsonResponse
    {
        $item = AccountsItem::with(['preferredVendor', 'creator', 'updater'])->find($id);
        if (!$item) {
            return $this->error('Item not found', 404);
        }

        return $this->success($item);
    }

    /**
     * Update the specified item.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $item = AccountsItem::find($id);
        if (!$item) {
            return $this->error('Item not found', 404);
        }

        $request->validate([
            'name'                 => 'nullable|string|max:255',
            'type'                 => 'nullable|in:Goods,Service,goods,service',
            'selling_price'        => 'nullable|numeric',
            'sellingPrice'         => 'nullable|numeric',
            'cost_price'           => 'nullable|numeric',
            'costPrice'            => 'nullable|numeric',
            'status'               => 'nullable|in:active,inactive,Active,Inactive',
        ]);

        $user = auth('api')->user();
        $payload = $this->formatItemPayload($request, $item);
        $payload['updated_by'] = $user?->id;

        $item->update($payload);

        $fresh = $item->fresh();
        try {
            $fresh->load('preferredVendor');
        } catch (\Throwable $e) {}

        return $this->success($fresh, 'Item updated successfully');
    }

    /**
     * Remove the specified item (soft delete).
     */
    public function destroy($id): JsonResponse
    {
        $item = AccountsItem::find($id);
        if (!$item) {
            return $this->error('Item not found', 404);
        }

        $item->delete();
        return $this->success(null, 'Item deleted successfully');
    }

    /**
     * Unified Export for Accounts Items (supports format=xlsx, csv, pdf).
     */
    public function export(Request $request)
    {
        $this->authenticateFromToken($request);

        $format = strtolower($request->input('format', 'xlsx'));
        if ($format === 'pdf') {
            return $this->exportPdf($request);
        }

        return $this->exportExcel($request);
    }

    /**
     * Export Accounts Items to Excel or CSV.
     */
    public function exportExcel(Request $request)
    {
        $this->authenticateFromToken($request);

        $items = $this->buildFilteredQuery($request)->get();
        $rows = $this->prepareExportRows($items);
        $filename = 'accounts_items_' . now()->format('YmdHis');

        $format = strtolower($request->input('format', 'xlsx'));
        if ($format === 'csv') {
            return Excel::download(new AccountsItemExport($rows), $filename . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return Excel::download(new AccountsItemExport($rows), $filename . '.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export Accounts Items to PDF.
     * Supports dynamic columns via columns[] parameter.
     */
    public function exportPdf(Request $request)
    {
        $this->authenticateFromToken($request);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $items = $this->buildFilteredQuery($request)->get();
        $requestedColumns = $request->input('columns');

        if (!empty($requestedColumns) && is_array($requestedColumns)) {
            [$headings, $rows] = $this->prepareCustomPdfRows($items, $requestedColumns);
        } else {
            $export = new AccountsItemExport($this->prepareExportRows($items));
            $headings = $export->headings();
            $rows = $export->array();
        }

        $filename = 'accounts_items_' . now()->format('YmdHis') . '.pdf';

        $pdf = Pdf::loadView('reports.generic_pdf', [
            'data'     => $rows,
            'headings' => $headings,
            'title'    => 'Accounts Items Report',
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Import Accounts Items from an Excel or CSV file.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $user = auth('api')->user();
        $companyId = $user?->company_id;
        $createdBy = $user?->id;

        $importer = new AccountsItemImport($companyId, $createdBy);

        try {
            Excel::import($importer, $request->file('file'));

            return $this->success([
                'imported_count' => $importer->getImportedCount(),
                'skipped_count'  => $importer->getSkippedCount(),
                'errors'         => $importer->getErrors(),
            ], 'Items imported successfully.');
        } catch (\Throwable $e) {
            return $this->error('Failed to import items: ' . $e->getMessage(), 422);
        }
    }

    // ── Private Helpers ────────────────────────────────────────────────────────

    private function buildFilteredQuery(Request $request)
    {
        $query = AccountsItem::query()->latest();

        try {
            $query->with('preferredVendor');
        } catch (\Throwable $e) {}

        $user = auth('api')->user();
        if ($user && $user->company_id) {
            $query->where(function ($q) use ($user) {
                $q->where('company_id', $user->company_id)
                    ->orWhereNull('company_id');
            });
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sales_description', 'like', "%{$search}%")
                    ->orWhere('purchase_description', 'like', "%{$search}%")
                    ->orWhere('preferred_vendor_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', ucfirst(strtolower($request->type)));
        }

        if ($request->filled('status')) {
            $query->where('status', strtolower($request->status));
        }

        if ($request->filled('source')) {
            $query->where('source', strtolower($request->source));
        }

        return $query;
    }

    private function formatItemPayload(Request $request, ?AccountsItem $existing = null): array
    {
        $type = $request->input('type', $existing?->type ?? 'Goods');
        $type = ucfirst(strtolower($type)) === 'Service' ? 'Service' : 'Goods';

        $sellingPrice = $request->input('sellingPrice') ?? $request->input('selling_price', $existing?->selling_price ?? 0);
        $costPrice = $request->input('costPrice') ?? $request->input('cost_price', $existing?->cost_price ?? 0);

        // Resolve vendor
        $vendorId = $request->input('preferredVendorId') ?? $request->input('preferred_vendor_id', $existing?->preferred_vendor_id);
        $vendorName = $request->input('preferredVendor') ?? $request->input('preferred_vendor_name', $existing?->preferred_vendor_name);

        if ($vendorId) {
            $vendor = AccountsVendor::find($vendorId);
            if ($vendor) {
                $vendorName = $vendor->name;
            }
        } elseif (!empty($vendorName)) {
            $vendor = AccountsVendor::where('name', $vendorName)->first();
            if ($vendor) {
                $vendorId = $vendor->id;
            }
        }

        return [
            'name'                  => $request->input('name', $existing?->name),
            'type'                  => $type,
            'usage_unit'            => $request->input('usageUnit') ?? $request->input('usage_unit', $existing?->usage_unit),
            'selling_price'         => (float) $sellingPrice,
            'selling_currency'      => $request->input('selling_currency', $existing?->selling_currency ?? 'INR'),
            'sales_account'         => $request->input('salesAccount') ?? $request->input('sales_account', $existing?->sales_account ?? 'Sales'),
            'sales_description'     => $request->input('salesDescription') ?? $request->input('sales_description', $existing?->sales_description),
            'cost_price'            => (float) $costPrice,
            'cost_currency'         => $request->input('cost_currency', $existing?->cost_currency ?? 'INR'),
            'purchase_account'      => $request->input('purchaseAccount') ?? $request->input('purchase_account', $existing?->purchase_account ?? 'Cost of Goods Sold'),
            'preferred_vendor_id'   => $vendorId,
            'preferred_vendor_name' => $vendorName,
            'purchase_description'  => $request->input('purchaseDescription') ?? $request->input('purchase_description', $existing?->purchase_description),
            'status'                => strtolower($request->input('status', $existing?->status ?? 'active')) === 'inactive' ? 'inactive' : 'active',
        ];
    }

    private function prepareExportRows($items): array
    {
        $rows = [];
        foreach ($items as $item) {
            $vendorName = $item->preferredVendor?->name ?? ($item->preferred_vendor_name ?? '-');

            $rows[] = [
                $item->name ?? '-',
                $item->type ?? 'Goods',
                $item->usage_unit ?? '-',
                number_format((float) ($item->selling_price ?? 0), 2),
                $item->selling_currency ?? 'INR',
                $item->sales_account ?? 'Sales',
                $item->sales_description ?? '',
                number_format((float) ($item->cost_price ?? 0), 2),
                $item->cost_currency ?? 'INR',
                $item->purchase_account ?? 'Cost of Goods Sold',
                $vendorName,
                $item->purchase_description ?? '',
                $item->status ?? 'active',
                $item->source ?? 'user',
            ];
        }
        return $rows;
    }

    private function prepareCustomPdfRows($items, array $requestedColumns): array
    {
        $headings = [];
        $columnKeys = [];

        foreach ($requestedColumns as $col) {
            $colName = trim((string) $col);
            if ($colName === '') {
                continue;
            }
            $headings[] = $colName;
            $columnKeys[] = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $colName));
        }

        if (empty($headings)) {
            $export = new AccountsItemExport($this->prepareExportRows($items));
            return [$export->headings(), $export->array()];
        }

        $rows = [];
        foreach ($items as $item) {
            $sellingSymbol = $this->getCurrencySymbol($item->selling_currency ?? 'INR');
            $costSymbol = $this->getCurrencySymbol($item->cost_currency ?? 'INR');
            $vendorName = $item->preferredVendor?->name ?? ($item->preferred_vendor_name ?? '-');

            $row = [];
            foreach ($columnKeys as $normKey) {
                $row[] = match ($normKey) {
                    'name', 'itemname' => $item->name ?? '-',
                    'type', 'itemtype' => $item->type ?? 'Goods',
                    'usageunit', 'unit', 'uom' => $item->usage_unit ?? '-',
                    'sellingprice', 'salesprice', 'price' => $sellingSymbol . number_format((float) ($item->selling_price ?? 0), 2),
                    'sellingcurrency' => $item->selling_currency ?? 'INR',
                    'salesaccount' => $item->sales_account ?? 'Sales',
                    'salesdescription', 'description' => $item->sales_description ?? '',
                    'costprice', 'purchaseprice', 'cost' => $costSymbol . number_format((float) ($item->cost_price ?? 0), 2),
                    'costcurrency' => $item->cost_currency ?? 'INR',
                    'purchaseaccount' => $item->purchase_account ?? 'Cost of Goods Sold',
                    'preferredvendor', 'vendor', 'vendorname' => $vendorName,
                    'purchasedescription' => $item->purchase_description ?? '',
                    'status' => $item->status ?? 'active',
                    'source' => $item->source ?? 'user',
                    'createdat', 'created' => $item->created_at ? $item->created_at->format('Y-m-d') : '-',
                    default => $item->{$normKey} ?? ($item->getAttribute($normKey) ?? '-')
                };
            }
            $rows[] = $row;
        }

        return [$headings, $rows];
    }

    private function getCurrencySymbol(?string $currencyCode): string
    {
        $code = strtoupper(trim((string) $currencyCode));

        return match ($code) {
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'AED ',
            'SAR' => 'SAR ',
            'JPY' => '¥',
            'RUB' => '₽',
            'CNY' => '¥',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            'SGD' => 'S$',
            default => $code ? ($code . ' ') : '₹',
        };
    }

    private function authenticateFromToken(Request $request): void
    {
        if (!$request->bearerToken() && $request->has('token')) {
            try {
                $user = auth('api')->setToken($request->token)->user();
                if ($user) {
                    auth('api')->setUser($user);
                }
            } catch (\Throwable $e) {}
        }

        if (!auth('api')->check()) {
            abort(403, 'Forbidden - Access denied. Please provide a valid token.');
        }
    }
}
