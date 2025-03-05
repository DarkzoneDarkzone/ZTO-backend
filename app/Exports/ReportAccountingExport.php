<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReportAccountingExport implements FromGenerator, WithMapping, WithHeadings, WithColumnFormatting
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
            $row->created_at,
            // $row->payment_no,
            $row->bill_reference,
            $row->amount,
            $row->cash,
            $row->transffer,
            $row->alipay,
            $row->wechat_pay,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_DATETIME,
            // 'B' => NumberFormat::FORMAT_NUMBER,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'D' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            // 'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }

    /**
     * Write code on Method
     *
     * @return response()
     */
    public function headings(): array
    {
        return ["Date", "Bill No.", "Total amount(LAK)", "Cash", "Transfer", "Alipay", "Wechat Pay"];
    }
}
