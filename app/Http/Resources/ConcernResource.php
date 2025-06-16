<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ConcernResource extends JsonResource
{
    public function toArray($request)
    {
        $ids = DB::table('product_filter_values')->where('filter_value_id',$this->id)->select(['product_filter_id'])->pluck('product_filter_id')->toArray();
        $product_ids=DB::table('product_filters')->whereIn('id', $ids)->select(['product_id'])->pluck('product_id')->toArray();
        // $product_ids = Product::where('brand_id', $this->id)->select(['id'])->get()->pluck('id')->toArray();
        $concerns = DB::table('product_filters')->whereIn('product_filters.product_id', $product_ids)
        ->where('product_filters.filter_id', 1)
        ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
        ->leftJoin('filter_values', 'filter_values.id', '=', 'product_filter_values.filter_value_id')
        ->select('filter_value_id', 'value', DB::raw('count(*) as total'))
        ->groupBy('value','filter_value_id')
        ->orderBy('total', 'desc')
        ->limit(10)
        ->get();
        $categories = DB::table('product_categories')->whereIn('product_id', $product_ids)
        // ->where('product_filters.filter_id', 4)
        ->leftJoin('categories', 'product_categories.category_id', '=', 'categories.id')
        // ->leftJoin('filter_values', 'filter_values.id', '=', 'product_filter_values.filter_value_id')
        ->select('category_id', 'name', DB::raw('count(*) as total'))
        ->groupBy('category_id','name')
        ->orderBy('total', 'desc')
        ->limit(10)
        ->get();
        
    $ingredients = DB::table('product_filters')->whereIn('product_filters.product_id', $product_ids)
        ->where('product_filters.filter_id', 4)
        ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
        ->leftJoin('filter_values', 'filter_values.id', '=', 'product_filter_values.filter_value_id')
        ->select('filter_value_id', 'value', DB::raw('count(*) as total'))
        ->groupBy('value','filter_value_id')
        ->orderBy('total', 'desc')
        ->limit(10)
        ->get();
        return [
            "id" => $this->id,
            "name" => $this->value,
            "concerns" => $concerns,
            "ingredients" => $ingredients,
            "categories"=>$categories,
            "logo" => uploaded_asset($this->logo),
            "slug" => $this->slug,
            'banner' => api_asset($this->banner),
            'description' => $this->description??"",
            'best_sell' => Product::where('brand_id', $this->id)->where('published', 1)->orderBy('is_best_sell', 'desc')->take(10)->get()->map(function ($data) {
                $today = date('Y-m-d');
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'name_ar' => $data->getTranslation('name', 'ar_QA'),
                    'brand' => $data->brand ? $data->brand->name : '',
                    'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                    'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                        strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,
                    'thumbnail_image' => api_asset($data->thumbnail_img),
                    'slug' => $data->slug,
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'stroked_price' => home_base_price($data, false),
                    'main_price' => home_discounted_base_price($data, false),
                    'discount_end_time' => home_discounted_end_time($data),
                    'links' => [
                        'details' => route('products.show', $data->slug),
                    ]
                ];
            }),
            'products' => Product::whereIn('id', $product_ids)->where('published', 1)->orderBy('num_of_sale', 'desc')->take($this->list_count)->get()->map(function ($data) {
                $today = date('Y-m-d');
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                  
                    'slug' => $data->slug,
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'stroked_price' => home_base_price($data, false),
                    'main_price' => home_discounted_base_price($data, false),
                  
                ];
            }),

        ];
    }
}
