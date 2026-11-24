<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeadExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
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
            'Lead ID',
            'Lead Name',
            'Lead Type',
            'Company Name',
            'Designation',
            'Industry',
            'Lead Source',
            'Lead Status',
            'Priority',
            'Email',
            'Primary Phone',
            'City',
            'State',
            'Country',
            'Assigned Salesperson',
            'Sales Team',
            'Expected Value',
            'Expected Closing Date',
            'Interested Products',
            'Next Follow-up Date',
            'Created At',
        ];
    }

    public function title(): string
    {
        return 'Leads Report';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1E3A8A']], // Dark Navy Blue header
            ],
        ];
    }
}
