<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OpportunityExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
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
            'Opportunity ID',
            'Opportunity Name',
            'Customer',
            'Contact Person',
            'Product / Service',
            'Estimated Value',
            'Currency',
            'Stage',
            'Probability (%)',
            'Expected Closing Date',
            'Assigned Salesperson',
            'Sales Team',
            'Priority',
            'Lead Source',
            'Competitor',
            'Decision Maker',
            'Customer Requirements',
            'Next Action',
            'Notes',
            'Created At',
        ];
    }

    public function title(): string
    {
        return 'Opportunities Report';
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
