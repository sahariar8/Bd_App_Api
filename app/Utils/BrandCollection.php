<?php

namespace App\Utils;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BrandCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'offer_title' => $data->offer_title,
                    'description'=> $data->description,
                    'meta_description'=> $data->meta_description,
                    'banner' => api_asset($data->banner),
                    'slug' => $data->slug,
                    'logo' => api_asset($data->logo),
                    'links' => [
                        'products' => route('api.products.brand', $data->id)
                    ]
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
