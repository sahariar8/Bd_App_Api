<?php

namespace App\Utils;

use App\Models\Product;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AppBrandOfferCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                $now = strtotime(date('d-m-Y H:i:s'));
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'offer_title' => $data->offer_title,

                    'slug' => $data->slug,
                    'logo' => api_asset($data->logo),
                    'feature_icon' => api_asset($data->feature_icon),
                    'feature_banner' => api_asset($data->offer_logo),
                    'image_background' => $data->image_background,
                    'bottom_background' => $data->bottom_background,
                    'font_color' => $data->font_color,
                    'featured' => Product::where('brand_id', $data->id)
    ->where('published', 1)
    ->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
    ->selectRaw('
        combo_type,
        thumbnail_img,
        (CASE WHEN discount_end_date > ' . $now . ' THEN unit_price - discount ELSE unit_price END) AS net_price,
        (CASE WHEN SUM(qty_available) > 0 THEN 1 ELSE 0 END) AS in_stock,
        discount_type,
        products.id,
        name,
        slug,
        unit_price,
        discount,
        discount_start_date,
        discount_end_date,
        SUM(qty_available) AS qty_available,
        brand_id,
        product_new_from,
        product_new_to
    ')
    ->groupBy(
        'products.id',
        'products.combo_type',
        'products.thumbnail_img',
        'products.discount_type',
        'products.name',
        'products.slug',
        'products.unit_price',
        'products.discount',
        'products.discount_start_date',
        'products.discount_end_date',
        'products.brand_id',
        'products.product_new_from',
        'products.product_new_to'
    )
    ->orderBy('in_stock', 'desc')
    ->orderBy('num_of_sale', 'desc')
    ->take(6)
    ->get()
    ->map(function ($product) {
                            $today = date('Y-m-d');
                            return [
                                'id' => $product->id,
                                'name' => $product->name,
                                'qty' => $product->qty_available,
                                'combo_type' => $product->combo_type,
                                'brand' => $product->brand ? $product->brand->name : '',
                                'brand_slug' => $product->brand ? $product->brand->slug : '',
                                'thumbnail_image' => api_asset($product->thumbnail_img),
                                'slug' => $product->slug,
                                'has_discount' => home_base_price($product, false) != home_discounted_base_price($product, false),
                                'stroked_price' => (int) home_discounted_base_price($product, false),
                                'main_price' => home_base_price($product, false),
                                'discount_end_time' => home_discounted_end_time($product),
                                'is_new' => $product->product_new_from <= $today && $product->product_new_to >= $today,
                                'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
                                    strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date,

                            ];
                        }),
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

