<?php

namespace App\Imports;

use App\Models\AccountsCustomer;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class AccountsCustomerImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
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

            // Support exact header name, slugified snake_case, or lowercase versions
            $displayName = $this->val($rowArr, ['display_name', 'display name']);
            if (empty($displayName)) {
                $this->skippedCount++;
                $this->errors[] = 'Row ' . ($index + 2) . ': Skipped because Display Name is missing.';
                continue;
            }

            $customerType = $this->val($rowArr, ['customer_type', 'customer type', 'type']);
            if ($customerType) {
                $customerType = strtolower($customerType) === 'individual' ? 'Individual' : 'Business';
            } else {
                $customerType = 'Business';
            }

            $data = [
                'company_id'          => $this->companyId,
                'created_by'          => $this->createdBy,
                'customer_type'       => $customerType,
                'display_name'        => trim($displayName),
                'salutation'          => $this->val($rowArr, ['salutation']),
                'first_name'          => $this->val($rowArr, ['first_name', 'first name']),
                'last_name'           => $this->val($rowArr, ['last_name', 'last name']),
                'company_name'        => $this->val($rowArr, ['company_name', 'company name']),
                'email'               => $this->val($rowArr, ['email', 'email_address', 'email address']),
                'country'             => $this->val($rowArr, ['country', 'country_region', 'country region']) ?: 'India',
                'work_phone'          => $this->val($rowArr, ['work_phone', 'work phone']),
                'mobile'              => $this->val($rowArr, ['mobile', 'mobile_phone', 'mobile phone', 'phone']),
                'gstin_pan'           => $this->val($rowArr, ['gstin_pan', 'gstin / pan', 'gstin/pan', 'gstin', 'pan']),
                'base_currency'       => $this->val($rowArr, ['currency', 'base_currency', 'base currency']) ?: 'INR',
                'opening_balance'     => is_numeric($this->val($rowArr, ['opening_balance', 'opening balance'])) ? (float) $this->val($rowArr, ['opening_balance', 'opening balance']) : 0,
                'payment_terms'       => $this->val($rowArr, ['payment_terms', 'payment terms']) ?: 'Due on receipt',

                // Billing Address
                'billing_attention'   => $this->val($rowArr, ['billing_attention', 'billing attention']),
                'billing_country'     => $this->val($rowArr, ['billing_country', 'billing country']),
                'billing_street1'     => $this->val($rowArr, ['billing_street_1', 'billing street 1', 'billing_street1']),
                'billing_street2'     => $this->val($rowArr, ['billing_street_2', 'billing street 2', 'billing_street2']),
                'billing_city'        => $this->val($rowArr, ['billing_city', 'billing city']),
                'billing_state'       => $this->val($rowArr, ['billing_state', 'billing state']),
                'billing_pin_code'    => $this->val($rowArr, ['billing_pin_code', 'billing pin code', 'billing_pincode']),
                'billing_phone'       => $this->val($rowArr, ['billing_phone', 'billing phone']),

                // Shipping Address
                'shipping_attention'  => $this->val($rowArr, ['shipping_attention', 'shipping attention']),
                'shipping_country'    => $this->val($rowArr, ['shipping_country', 'shipping country']),
                'shipping_street1'    => $this->val($rowArr, ['shipping_street_1', 'shipping street 1', 'shipping_street1']),
                'shipping_street2'    => $this->val($rowArr, ['shipping_street_2', 'shipping street 2', 'shipping_street2']),
                'shipping_city'       => $this->val($rowArr, ['shipping_city', 'shipping city']),
                'shipping_state'      => $this->val($rowArr, ['shipping_state', 'shipping state']),
                'shipping_pin_code'   => $this->val($rowArr, ['shipping_pin_code', 'shipping pin code', 'shipping_pincode']),
                'shipping_phone'      => $this->val($rowArr, ['shipping_phone', 'shipping phone']),

                'remarks'             => $this->val($rowArr, ['remarks', 'internal_notes', 'internal notes']),
            ];

            try {
                // If customer with same display_name and company_id exists, update it; otherwise create
                AccountsCustomer::updateOrCreate(
                    [
                        'company_id'   => $this->companyId,
                        'display_name' => trim($displayName),
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

    /**
     * Retrieve matching value by candidate keys.
     */
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
