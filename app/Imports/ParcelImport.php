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

class ParcelImport implements ToArray, WithValidation, WithStartRow, SkipsOnError, SkipsOnFailure, SkipsEmptyRows
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

    public function array(array $rows)
    {

        foreach ($rows as $key => $row) {
            $phone = $row[6];
            // if (str_starts_with($phone, '85620')) {
            //     $phone = str_replace("85620", "", $row[6]);
            // }
            $this->data[] = [
                'zto_track_no' => $row[0],
                'track_no' => $row[1],
                'weight' => (float)$row[2] > (float)$row[3] ? (float)$row[2] : (float)$row[3],
                'price' => $row[4],
                'name' => $row[5],
                'phone' => $phone,
                'address' => null,
                'receipt_at' => $row[7],
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
            '0' => 'required',
            '1' => 'required',
            '2' => 'required|numeric',
            '3' => 'required|numeric',
            '4' => 'required',
            '5' => 'required',
            '6' => 'required',
            '7' => 'required',
        ];
    }

    /**
     * @return array
     */
    public function customValidationMessages()
    {
        return [
            '0' => 'The ZTO Track Number field is required.',
            '2' => 'The Recording weight field is required and must be numeric.',
            '3' => 'The Volume weight field is required and must be numeric.',
            '4' => 'The Price field is required.',
            '5' => 'The Recipient field is required.',
            '6' => 'The Phone Number field is required.',
            '7' => 'The Center dispatch time field is required.',
        ];
    }
}
