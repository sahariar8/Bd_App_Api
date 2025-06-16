<?php

namespace App\Utils;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class ConcernCollection extends ResourceCollection
{
    public function toArray($request)
{
    return [
        'data' => $this->collection->map(function ($data) {
            $now = Carbon::now()->timestamp;
            $today = Carbon::today()->toDateString();

            $ids = DB::table('product_filter_values')
                ->where('filter_value_id', $data->id)
                ->pluck('product_filter_id')
                ->toArray();

            $product_ids = DB::table('product_filters')
                ->whereIn('id', $ids)
                ->pluck('product_id')
                ->unique()
                ->values()
                ->toArray();

            $featured = Product::where('published', 1)
                ->whereIn('products.id', $product_ids)
                ->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
                ->selectRaw("
                    combo_type,
                    thumbnail_img,
                    (CASE WHEN discount_end_date > {$now} THEN unit_price - discount ELSE unit_price END) as net_price,
                    (CASE WHEN SUM(qty_available) > 0 THEN 1 ELSE 0 END) as in_stock,
                    discount_type,
                    products.id,
                    name,
                    slug,
                    unit_price,
                    discount,
                    discount_start_date,
                    discount_end_date,
                    SUM(qty_available) as qty_available,
                    brand_id,
                    product_new_from,
                    product_new_to
                ")
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
                ->orderByDesc('in_stock')
                ->orderByDesc('num_of_sale')
                ->take(6)
                ->with('brand')
                ->get()
                ->map(function ($product) use ($today) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'qty' => $product->qty_available,
                        'combo_type' => $product->combo_type,
                        'brand' => $product->brand?->name ?? '',
                        'brand_slug' => $product->brand?->slug ?? '',
                        'thumbnail_image' => api_asset($product->thumbnail_img),
                        'slug' => $product->slug,
                        'has_discount' => home_base_price($product, false) != home_discounted_base_price($product, false),
                        'stroked_price' => home_base_price($product, false),
                        'main_price' => home_discounted_base_price($product, false),
                        'discount_end_time' => home_discounted_end_time($product),
                        'is_new' => $product->product_new_from <= $today && $product->product_new_to >= $today,
                        'is_offer' => strtotime(now()) >= $product->discount_start_date &&
                                      strtotime(now()) <= $product->discount_end_date,
                    ];
                });

            return [
                'id' => $data->id,
                'name' => $data->value,
                'title' => $data->feature_title,
                'banner' => api_asset($data->image),
                'image' => api_asset($data->image),
                'feature_icon' => api_asset($data->feature_icon),
                'slug' => $data->slug,
                'featured' => $featured,
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
