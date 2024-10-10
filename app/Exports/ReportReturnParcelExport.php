<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReportReturnParcelExport implements FromGenerator, WithMapping, WithHeadings, WithColumnFormatting
{
    use Exportable;

    private $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function generator(): \Generator
    {
        $data = $this->query;
        foreach ($data as $row) {
            yield $row;
        }
    }

    /**
     * @param Parcel $parcel
     */
    public function map($row): array
    {
        return [
            $row->track_no,
            $row->created_at,
            $row->delivery_car_no,
            $row->delivery_person,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'B' => NumberFormat::FORMAT_DATE_DATETIME,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
        ];
    }

    /**
     * Write code on Method
     *
     * @return response()
     */
    public function headings(): array
    {
        return ["Track No", "Return Date", "Delivery Car No", "Delivery Person"];
    }
}
