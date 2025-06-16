<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class CategoryResource extends JsonResource
{
   public function toArray($request)
    {
        if (request()->has("test")) {
            DB::enableQueryLog();
        }
        $product_ids = ProductCategory::where('category_id', $this->id)->pluck('product_id')->toArray();
        $brands = DB::table('products')->whereIn('products.id', $product_ids)->where('published', 1)
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->select('brand_id', DB::raw('count(*) as total'), 'brands.name')
            ->groupBy('brand_id','brands.name')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $concerns = DB::table('product_filters')->whereIn('product_filters.product_id', $product_ids)
            ->where('product_filters.filter_id', 1)
            ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
            ->leftJoin('filter_values', 'filter_values.id', '=', 'product_filter_values.filter_value_id')
            ->select('filter_value_id', 'value', DB::raw('count(*) as total'))
            ->groupBy('filter_value_id','value')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
        $ingredients = DB::table('product_filters')->whereIn('product_filters.product_id', $product_ids)
            ->where('product_filters.filter_id', 4)
            ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
            ->leftJoin('filter_values', 'filter_values.id', '=', 'product_filter_values.filter_value_id')
            ->select('filter_value_id', 'value', DB::raw('count(*) as total'))
            ->groupBy('filter_value_id','value')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
        $filter_ids = DB::table('filter_categories_pivot')->where('category_id', $this->id)->whereNotIn('filter_id', [1, 4])->select('filter_id')->get()->pluck('filter_id')->toArray();
        $all_filters = [];
        foreach ($filter_ids as $id) {
            $name = DB::table('filters')->find($id)->name;
            $filterData = DB::table('product_filters')->whereIn('product_filters.product_id', $product_ids)
                ->where('product_filters.filter_id', $id)
                ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                ->leftJoin('filter_values', 'filter_values.id', '=', 'product_filter_values.filter_value_id')
                ->select('filter_value_id', 'value', DB::raw('count(*) as total'))
                ->groupBy('filter_value_id','value')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get();
            array_push($all_filters, [
                "name" => $name,
                "data" => $filterData
            ]);
        }
        $tree = [];
        if ($this->parent_id != 0) {
            $p1 = Category::find($this->parent_id);
            if ($p1->parent_id != 0) {
                $p2 = Category::find($p1->parent_id);

                if ($p2->parent_id != 0) {
                    $p3 = Category::find($p2->parent_id);
                    array_push($tree, [
                        "name" => $p3->name,
                        "slug" => $p3->slug,
                    ]);
                }
                array_push($tree, [
                    "name" => $p2->name,
                    "slug" => $p2->slug,
                ]);
            }
            array_push($tree, [
                "name" => $p1->name,
                "slug" => $p1->slug,
            ]);
        }
        array_push($tree, [
            "name" => $this->name,
            "slug" => "",
        ]);
        $now = strtotime(date('d-m-Y H:i:s'));

        $result = [
            "id" => $this->id,
            "no_follow" => $this->no_follow,
            "no_index" => $this->no_index,

            "filters" => array_filter($all_filters, function ($filt) {
                return count($filt["data"]) > 0;
            }),
            'overview_title' => $this->overview_title,
            'overview_description' => $this->overview_description,
            'products_title' => $this->products_title,
            "brands" => $brands->map(function ($brand) {
                return [
                    "brand_id" => $brand->brand_id,
                    "total" => $brand->total,
                    "name" => $brand->name,
                    "value" => $brand->name,

                    "filter_value_id" => $brand->brand_id,

                ];
            }),
            "concerns" => $concerns,
            "ingredients" => $ingredients,
            "name" => $this->name,

            "banner" => str_replace("admin.", "cdn.", uploaded_asset($this->banner)),
            "slug" => $this->slug,
            "parent" => get_parent_category_name($this->parent_id),
            "description" => $this->description,
            'meta_title' => $this->meta_title ? $this->meta_title : $this->name . " | Beauty Booth",
            "meta_description" => $this->meta_description ? $this->meta_description : "",
            "meta_keywords" => $this->meta_keywords ? $this->meta_keywords : "",
            "meta_canonical" => $this->meta_canonical,


            "tree" => $tree,
            "subcategories" => $this->categories->take(8)->map(function ($data) {
                return [
                    "name" => $data->name,
                    "title" => $data->name,

                    "slug" => $data->slug,
                    "url" => $data->slug,

                    "icon" => str_replace("admin.", "cdn.", uploaded_asset($data->icon)),
                    "image" => str_replace("admin.", "cdn.", api_asset($data->icon)),

                    "banner" => str_replace("admin.", "cdn.", uploaded_asset($data->banner)),
                    "parent" => get_parent_category_name($data->parent_id)
                ];
            }),
            'best_sell' => Product::whereIn('products.id', $product_ids)->where('published', 1)->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
                ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')->orderBy('in_stock', 'desc')->orderBy('is_best_sell', 'desc')->take(10)->get()->map(function ($data) {
                    $today = date('Y-m-d');
                    return [
                        'id' => $data->id,
                        'name' => $data->name,
                        'in_stock' => $data->in_stock,
                        'qty' => $data->qty_available,
                        'combo_type' => $data->combo_type,
                        'brand' => $data->brand ? $data->brand->name : '',
                        'brand_slug' => $data->brand ? $data->brand->slug : '',

                        'thumbnail_image' => api_asset($data->thumbnail_img),
                        'slug' => $data->slug,
                        'net_price' => (int) $data->net_price,

                        'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                        'stroked_price' => home_base_price($data, false),
                        'main_price' => (int) home_discounted_base_price($data, false),
                        'discount_end_time' => home_discounted_end_time($data),
                        'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                        'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                            strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,
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
                    'main_price' => (int) home_discounted_base_price($data, false),

                ];
            }),

        ];
        if (request()->has("test")) {
            $queries = DB::getQueryLog();
            // dd($queries);
        }
        return $result;
    }
}
