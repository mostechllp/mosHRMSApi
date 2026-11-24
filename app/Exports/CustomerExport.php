<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CustomerExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
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
            'Customer ID',
            'Company Name',
            'Customer Type',
            'Industry',
            'Registration No.',
            'Tax / GST No.',
            'Website',
            'Customer Status',
            'Customer Since',
            'Account Manager',
            'Billing Address',
            'Shipping Address',
            'City',
            'State',
            'Country',
            'Postal Code',
            'Contact Person',
            'Contact Designation',
            'Contact Email',
            'Contact Phone',
            'Alternate Phone',
            'Preferred Communication',
            'Customer Category',
            'Customer Source',
            'Payment Terms',
            'Credit Limit',
            'Created At',
        ];
    }

    public function title(): string
    {
        return 'Customers Report';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1B5E20']], // Dark Green header
            ],
        ];
    }
}
