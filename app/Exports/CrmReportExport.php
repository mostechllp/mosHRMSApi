<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CrmReportExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize, WithTitle
{
    protected array $rows;
    protected array $headings;
    protected string $title;

    public function __construct(array $rows, array $headings, string $title = 'CRM Report')
    {
        $this->rows = $rows;
        $this->headings = $headings;
        $this->title = substr(preg_replace('/[^A-Za-z0-9 _-]/', '', $title), 0, 31);
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title ?: 'Report';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A8A'],
                ],
            ],
        ];
    }
}
