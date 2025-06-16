<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FreeGiftCollection extends ResourceCollection
{
     public function toArray($request)
    {

        return [
            'banner' => [
                "desktop" =>
                    [
                        "photo" => str_replace("admin.", "cdn.", uploaded_asset(get_setting('free_gift_desktop')))
                    ]
                ,
                "mobile" =>
                    [
                        "photo" => str_replace("admin.", "cdn.", uploaded_asset(get_setting('free_gift_mobile')))
                    ]

            ],
            'data' => $this->collection->map(function ($data) {
                if ($data->type == 'cart') {
                    $name = "Cart";
                    $slug = "/";
                } else {
                    $name = $data->type == 'product' ? $data->product->name : $data->brand->name;
                    $slug = $data->type == 'product' ? $data->product->slug : $data->brand->slug;
                }
                $stock = 0;
                if (DB::connection('mysql2')->table('variation_location_details')->where('product_id', $data->gift->id)->first()) {
                    $stock = (int) DB::connection('mysql2')->table('variation_location_details')->where('product_id', $data->gift->id)->sum("qty_available");
                }
                return [
                    'id' => $data->id,
                    'title' => $data->title,
                    'type' => $data->type,
                    'name' => $name,
                    'slug' => $slug,
                    'gift_stock' => (int) $stock,
                    'gift_slug' => $data->gift->slug,
                    'gift_name' => $data->gift->name,
                    'gift_name_ar' => $data->gift->getTranslation('name', 'ar_QA'),
                    // 'name_ar' => $data->getTranslation('name', 'ar_QA'),,
                    'brand_name' => $data->gift->brand ? $data->gift->brand->name : null,
                    'brand_slug' => $data->gift->brand ? $data->gift->brand->slug : null,
                    'has_discount' => home_base_price($data->gift, false) != home_discounted_base_price($data->gift, false),
                    'stroked_price' => home_base_price($data->gift, false),
                    'main_price' => home_discounted_base_price($data->gift, false),
                    'price' => home_discounted_base_price($data->gift, false),
                    'gift_image' => api_asset($data->gift->thumbnail_img),
                    'banner' => api_asset($data->gift->thumbnail_img)
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
