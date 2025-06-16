<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class BrandResource extends JsonResource
{
   public function toArray($request)
    {
        $product_ids = Product::where('brand_id', $this->id)->select(['id'])->get()->pluck('id')->toArray();
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
        $meta_title=$this->meta_title;
        if(!$this->meta_title||$this->meta_title=="."){
            $meta_title=$this->name. " | Beauty Booth";
        }
        $now=strtotime(date('d-m-Y H:i:s'));
        return [
            "id" => $this->id,
            "no_follow" => $this->no_follow,
            "no_index" => $this->no_index,

            "meta_title" => $meta_title,
            "meta_description" => $this->meta_description?$this->meta_description:'',
            "meta_keywords" => $this->meta_keywords?$this->meta_keywords:"",
            "meta_canonical" => $this->meta_canonical?$this->meta_canonical:"",
            'overview_title' => $this->overview_title,
            'products_title' => $this->products_title,
            'overview_description' => $this->overview_description,
            "name" => $this->name,
            "concerns" => $concerns,
            "ingredients" => $ingredients,
            "categories" => $categories->map(function ($cat) {
                return [
                    "filter_value_id" => $cat->category_id,
                    "category_id" => $cat->category_id,
                    "name" => $cat->name,
                    "total" => $cat->total,
                    "value" => $cat->name,

                ];
            }),
            "logo" => str_replace("admin.","cdn.",uploaded_asset($this->logo)),
            "slug" => $this->slug,
            'banner' => str_replace("admin.","cdn.",api_asset($this->banner)),
            'description' => $this->description??'',
            'best_sell' => Product::where('brand_id', $this->id)->where('published', 1)->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')
            ->groupBy('products.id','combo_type','thumbnail_img','slug','name','discount_end_date','unit_price','discount','discount_type','discount_start_date','brand_id','product_new_from','product_new_to')->orderBy('in_stock','desc')->orderBy('is_best_sell', 'desc')->take(10)->get()->map(function ($data) {
                $today = date('Y-m-d');
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'in_stock'=>$data->in_stock,
                    'qty' => $data->qty_available ?? 0,
                    'combo_type' => $data->combo_type,
                    'brand' => $data->brand ? $data->brand->name : '',
                    'brand_slug' => $data->brand ? $data->brand->slug : '',


                    'thumbnail_image' => api_asset($data->thumbnail_img),
                    'slug' => $data->slug,
                    'net_price' => $data->net_price,

                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'stroked_price' => home_base_price($data, false),
                    'main_price' => home_discounted_base_price($data, false),
                    'discount_end_time' => home_discounted_end_time($data),
                    'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                    'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                        strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date&&$data->discount,
                    'links' => [
                        'details' => route('products.show', $data->slug),
                    ]
                ];
            }),
            'products' => Product::where('brand_id', $this->id)->where('published', 1)->orderBy('num_of_sale', 'desc')->take($this->list_count)->get()->map(function ($data) {
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
