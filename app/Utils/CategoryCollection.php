<?php

namespace App\Utils;

use Illuminate\Http\Resources\Json\ResourceCollection;


class CategoryCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'banner' => api_asset($data->banner),
                    'icon' => api_asset($data->icon),
                    'slug' => $data->slug,
                    'number_of_children' => CategoryUtility::get_immediate_children_count($data->id),
                    'links' => [
                        'products' => route('api.products.category', $data->id),
                        'sub_categories' => route('subCategories.index', $data->id)
                    ],
                    'sub' => collect($data->categories)->map(function ($sub) {
                        return [
                            'id' => $sub->id,
                            'name' => $sub->name,
                            'banner' => api_asset($sub->banner),
                            'icon' => api_asset($sub->icon),
                            'slug' => $sub->slug,
                            'number_of_children' => CategoryUtility::get_immediate_children_count($sub->id),
                            'links' => [
                                'products' => route('api.products.category', $sub->id),
                                'sub_categories' => route('subCategories.index', $sub->id)
                            ]
                        ];
                    })
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
