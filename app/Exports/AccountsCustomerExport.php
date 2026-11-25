<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AccountsCustomerExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected array $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Customer Type',
            'Display Name',
            'Salutation',
            'First Name',
            'Last Name',
            'Company Name',
            'Email',
            'Country',
            'Work Phone',
            'Mobile',
            'GSTIN / PAN',
            'Currency',
            'Opening Balance',
            'Payment Terms',
            'Billing Attention',
            'Billing Country',
            'Billing Street 1',
            'Billing Street 2',
            'Billing City',
            'Billing State',
            'Billing Pin Code',
            'Billing Phone',
            'Shipping Attention',
            'Shipping Country',
            'Shipping Street 1',
            'Shipping Street 2',
            'Shipping City',
            'Shipping State',
            'Shipping Pin Code',
            'Shipping Phone',
            'Remarks',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A5F'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Customers';
    }
}
