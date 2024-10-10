<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportReturnParcelCollection extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'track_no' => $this->Parcel ? $this->Parcel->track_no : null,
            'return_date' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : '',
            'delivery_car_no' => $this->car_number,
            'delivery_person' => $this->driver_name,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : '',
            'updated_at' => $this->updated_at,
        ];
    }
}
