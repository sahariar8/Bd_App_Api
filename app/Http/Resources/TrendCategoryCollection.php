<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TrendCategoryCollection extends ResourceCollection
{

    public function toArray(Request $request): array
    {

        if (request()->has('slice')) {
            return [
                'data' => $this->collection->map(function ($data) {
                    return [
                        'title' => $data->name,
                        'image' => api_asset($data->getRawOriginal('icon')),
                        'url' => $data->slug,

                    ];
                })
            ];
        } else {

            return [
                'data' => array_chunk($this->collection->map(function ($data) {
                    return [
                        'title' => $data->name,
                        'image' => api_asset($data->getRawOriginal('icon')),
                        'url' => $data->slug,

                    ];
                })->toArray(), 2)
            ];
        }
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200
        ];
    }
}
