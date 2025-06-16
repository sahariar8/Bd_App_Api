<?php

namespace App\Utils;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCardCollection extends ResourceCollection
{
    public function toArray($request)
    {
        $today = date('Y-m-d');
        return [
            'data' => $this->collection->map(function ($data) use ($today) {
                return [
                    'id' => $data->id,
                    'in_stock' => $data->in_stock ?? false,
                    'name' => $data->name,
                    'brand' => $data->brand ? $data->brand->name : '',
                    'brand_slug' => $data->brand ? $data->brand->slug : '',


                    'thumbnail_image' => api_asset($data->thumbnail_img),
                    'slug' => $data->slug,
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'stroked_price' => home_base_price($data, false),
                    'main_price' => home_discounted_base_price($data, false),
                    'discount_end_time' => home_discounted_end_time($data),
                    'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                    'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                        strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,
                    'links' => [
                        'details' => route('products.show', $data->slug),
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