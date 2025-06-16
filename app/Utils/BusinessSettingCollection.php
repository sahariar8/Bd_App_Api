<?php

namespace App\Utils;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BusinessSettingCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {
                return [
                    'type' => $data->type,
                    'value' => $data->type == 'verification_form' ? json_decode($data->value) : $data->value
                ];
            })
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200
        ];
    }
}
