<?php

namespace App\Utils;

use App\Models\Product;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class AppConcernCollection extends ResourceCollection
{
    public function toArray($request)
    {

        return [
            'data' => $this->collection->map(function ($data) {
                $now = strtotime(date('d-m-Y H:i:s'));
                $ids = DB::table('product_filter_values')->where('filter_value_id', $data->id)->select(['product_filter_id'])->pluck('product_filter_id')->toArray();
                $product_ids = DB::table('product_filters')->whereIn('id', $ids)->select(['product_id'])->pluck('product_id')->toArray();
                return [
                    'id' => $data->id,
                    'name' => $data->value,
                    'title' => $data->feature_title,
                    'banner' => api_asset($data->image),
                    'image' => api_asset($data->image),

                    'feature_icon' => api_asset($data->feature_icon),
                    'slug' => $data->slug,

                    // 'featured' => Product::where('published', 1)->whereIn('products.id', $product_ids)->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
                    //     ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')
                    //     ->orderBy('in_stock', 'desc')
                    //     ->orderBy('num_of_sale', 'desc')
                    //     ->take(6)->get()

                    'featured' => Product::where('published', 1)
                        ->whereIn('products.id', $product_ids)
                        ->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
                        ->selectRaw('
                                    products.id,
                                    products.name,
                                    products.slug,
                                    products.unit_price,
                                    products.discount,
                                    products.discount_start_date,
                                    products.discount_end_date,
                                    products.discount_type,
                                    products.thumbnail_img,
                                    products.combo_type,
                                    products.brand_id,
                                    products.product_new_from,
                                    products.product_new_to,
                                    SUM(vld.qty_available) as qty_available,
                                    (CASE WHEN SUM(vld.qty_available) > 0 THEN 1 ELSE 0 END) as in_stock,
                                    (CASE WHEN discount_end_date > ' . $now . ' THEN unit_price - discount ELSE unit_price END) as net_price
                                ')
                        ->groupBy(
                            'products.id',
                            'products.name',
                            'products.slug',
                            'products.unit_price',
                            'products.discount',
                            'products.discount_start_date',
                            'products.discount_end_date',
                            'products.discount_type',
                            'products.thumbnail_img',
                            'products.combo_type',
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
                                'stroked_price' => home_discounted_base_price($product, false),
                                'main_price' => home_base_price($product, false),
                                'discount_end_time' => home_discounted_end_time($product),
                                'is_new' => $product->product_new_from <= $today && $product->product_new_to >= $today,
                                'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
                                    strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date,

                            ];
                        }),

                ];
            })->toArray()
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
