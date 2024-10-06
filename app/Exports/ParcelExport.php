<?php

namespace App\Exports;

use App\Models\Parcel;
use Maatwebsite\Excel\Concerns\FromCollection;

class ParcelExport implements FromCollection
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Parcel::all();
    }

    /**
     * Write code on Method
     *
     * @return response()
     */
    public function headings(): array
    {
        return ["ID", "Name", "Email"];
    }
}
