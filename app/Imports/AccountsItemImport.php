<?php

namespace App\Imports;

use App\Models\AccountsItem;
use App\Models\AccountsVendor;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AccountsItemImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    protected ?int $companyId;
    protected ?int $createdBy;
    protected int $importedCount = 0;
    protected int $skippedCount = 0;
    protected array $errors = [];

    public function __construct(?int $companyId = null, ?int $createdBy = null)
    {
        $this->companyId = $companyId;
        $this->createdBy = $createdBy;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowArr = $row->toArray();

            $name = $this->val($rowArr, ['name', 'item_name', 'item name']);
            if (empty($name)) {
                $this->skippedCount++;
                $this->errors[] = 'Row ' . ($index + 2) . ': Skipped because Item Name is missing.';
                continue;
            }

            $type = $this->val($rowArr, ['type', 'item_type', 'item type']) ?: 'Goods';
            $type = ucfirst(strtolower($type)) === 'Service' ? 'Service' : 'Goods';

            // Resolve Preferred Vendor by name or id if present
            $vendorId = null;
            $vendorName = $this->val($rowArr, ['preferred_vendor', 'preferred vendor', 'vendor', 'vendor_name', 'vendor name']);
            if (!empty($vendorName)) {
                $vendor = AccountsVendor::where(function ($q) {
                    if ($this->companyId) {
                        $q->where('company_id', $this->companyId)->orWhereNull('company_id');
                    }
                })->where(function ($q) use ($vendorName) {
                    $q->where('name', $vendorName)->orWhere('company_name', $vendorName);
                })->first();

                if ($vendor) {
                    $vendorId = $vendor->id;
                    $vendorName = $vendor->name;
                }
            }

            $sellingPrice = $this->val($rowArr, ['selling_price', 'selling price', 'sales_price', 'sales price', 'price']);
            $costPrice = $this->val($rowArr, ['cost_price', 'cost price', 'purchase_price', 'purchase price', 'cost']);

            $data = [
                'company_id'             => $this->companyId,
                'created_by'             => $this->createdBy,
                'name'                   => trim($name),
                'type'                   => $type,
                'usage_unit'             => $this->val($rowArr, ['usage_unit', 'usage unit', 'unit', 'uom']),
                'selling_price'          => is_numeric($sellingPrice) ? (float) $sellingPrice : 0.00,
                'selling_currency'       => $this->val($rowArr, ['selling_currency', 'selling currency']) ?: 'INR',
                'sales_account'          => $this->val($rowArr, ['sales_account', 'sales account']) ?: 'Sales',
                'sales_description'      => $this->val($rowArr, ['sales_description', 'sales description', 'description']),
                'cost_price'             => is_numeric($costPrice) ? (float) $costPrice : 0.00,
                'cost_currency'          => $this->val($rowArr, ['cost_currency', 'cost currency']) ?: 'INR',
                'purchase_account'       => $this->val($rowArr, ['purchase_account', 'purchase account']) ?: 'Cost of Goods Sold',
                'preferred_vendor_id'    => $vendorId,
                'preferred_vendor_name'  => $vendorName,
                'purchase_description'   => $this->val($rowArr, ['purchase_description', 'purchase description']),
                'status'                 => strtolower($this->val($rowArr, ['status']) ?? '') === 'inactive' ? 'inactive' : 'active',
                'source'                 => 'import',
            ];

            try {
                AccountsItem::updateOrCreate(
                    [
                        'company_id' => $this->companyId,
                        'name'       => trim($name),
                    ],
                    $data
                );
                $this->importedCount++;
            } catch (\Throwable $e) {
                $this->skippedCount++;
                $this->errors[] = 'Row ' . ($index + 2) . ': ' . $e->getMessage();
            }
        }
    }

    protected function val(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return (string) $row[$key];
            }
            $cleanKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $key));
            foreach ($row as $k => $v) {
                $normK = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', (string) $k));
                if ($normK === $cleanKey && $v !== null && $v !== '') {
                    return (string) $v;
                }
            }
        }
        return null;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
