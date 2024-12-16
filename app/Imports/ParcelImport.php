<?php

namespace App\Imports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ParcelImport implements ToArray, WithValidation, WithStartRow, SkipsOnError, SkipsOnFailure, SkipsEmptyRows, WithHeadingRow
{
    use Importable, SkipsErrors, SkipsFailures;

    private $data;

    public function __construct()
    {
        $this->data = [];
    }

    public function onError(\Throwable $e)
    {
        // Handle the exception how you'd like.
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function array(array $rows)
    {
        foreach ($rows as $key => $row) {
            $phone = $row['recipients_phone_number'];
            // if (str_starts_with($phone, '85620')) {
            //     $phone = str_replace("85620", "", $row[6]);
            // }
            $this->data[] = [
                'zto_track_no' => $row['zto_tracking_nuber'],
                'track_no' => $row['tracking_nuber'],
                'weight' => (float)$row['recording_weight'] > (float)$row['volume_weight'] ? (float)$row['recording_weight'] : (float)$row['volume_weight'],
                'price' => $row['cash_on_delivery'],
                'name' => $row['recipient'],
                'phone' => $phone,
                'address' => null,
                'receipt_at' => $row['center_dispatch_time'],
                'active' => true,
                'status' => 'pending',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }
    }

    public function startRow(): int
    {
        return 2;
    }

    public function getArray(): array
    {
        return $this->data;
    }

    public function rules(): array
    {
        return [
            'zto_tracking_nuber' => 'required',
            'tracking_nuber' => 'required',
            'recording_weight' => 'required|numeric',
            'volume_weight' => 'required|numeric',
            // 'cash_on_delivery' => 'required',
            'recipient' => 'required',
            'recipients_phone_number' => 'required',
            'center_dispatch_time' => 'required|date_format:Y-m-d H:i:s',
        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            'zto_tracking_nuber' => 'The ZTO Track Number field is required.',
            'recording_weight' => 'The Recording weight field is required and must be numeric.',
            'volume_weight' => 'The Volume weight field is required and must be numeric.',
            // '4' => 'The Price field is required.',
            'recipient' => 'The Recipient field is required.',
            'recipients_phone_number' => 'The Phone Number field is required.',
            'center_dispatch_time' => 'The Center dispatch time field is required and format must be Y-m-d H:i:s',
        ];
    }
}
