<?php

namespace App\Imports;

use App\Models\Parcel;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ParcelImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        return new Parcel([
            'track_no' => $row['track_no'],
            'phone' => $row['phone'],
            'weight' => $row['weight'] ?? 0,
            'customer_id' => $row['customer_id'] ?? 1,
            'status' => 'pending',
            'active' => false,
        ]);
    }

    public function rules(): array
    {
        return [
            // 'track_no' => 'required',
            // 'phone' => 'required|numeric',
            // 'weight' => 'required|numeric'
        ];
    }
}
