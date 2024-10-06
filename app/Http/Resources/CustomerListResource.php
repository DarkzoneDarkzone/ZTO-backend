<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // $data_json = array();
        // foreach ($request->data as $resource) {
        //     array_push($data_json, [
        //             'id' => $resource->id,
        //             "name" => $resource->name,
        //             "phone" => $resource->phone,
        //             'address' => $resource->address,
        //             'level_data' => $resource->CustomerLevel,
        //             'verify' => $resource->verify,
        //             'active' => $resource->active,
        //             'created_at' => $resource->created_at ? $resource->created_at->format('Y-m-d H:i:s') : '',
        //             'updated_at' => $resource->updated_at ? $resource->updated_at->format('Y-m-d H:i:s') : '',
        //     ]);
        // }
        // $request->data = $data_json;
        // return $request;
        return [
            'id' => $this->id,
            "name" => $this->name,
            "phone" => $this->phone,
            'address' => $this->address,
            'level_data' => $this->CustomerLevel,
            'verify' => $this->verify,
            'active' => $this->active,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : '',
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : '',
        ];
    
    }
}
