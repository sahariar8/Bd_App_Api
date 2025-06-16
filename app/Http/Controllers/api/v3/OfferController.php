<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandOfferCollection;
use App\Models\Brand;
use App\Models\CampaignProduct;
use App\Models\FilterValue;
use App\Models\FreeGift;
use App\Models\Product;
use App\Utils\ConcernCollection;
use App\Utils\FreeGiftCollection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
     public function freeGiftList()
    {
        $freeGifts = FreeGift::whereHas('gift', function ($q) {

            $q->where('published', 1);
        })->latest()->get();
        return new FreeGiftCollection($freeGifts);
    }

     public function bestData()
    {

        $product_ids = Product::where('is_best_sell', 1)->get()->pluck('id')->toArray();
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
        $brands = DB::table('products')->whereIn('products.id', $product_ids)->where('published', 1)
        ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
        ->select('brand_id', DB::raw('count(*) as total'), 'brands.name')
        ->groupBy('brand_id','name')
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
        return response()->json([
            'data' => [
                'banner'=> [
                    "desktop"=>
                        [
                            "photo"=>str_replace("admin.","cdn.",uploaded_asset(get_setting('best_sale_desktop')))
                        ]
                        ,
                        "mobile"=>
                            [
                                "photo"=>str_replace("admin.","cdn.",uploaded_asset(get_setting('best_sale_mobile')))
                            ]
                        
                ], 
                'concerns'=>$concerns,
                'brands'=>$brands->map(function($brand){
                    return [
                        "brand_id"=>$brand->brand_id,
                        "name"=>$brand->name,
                        "total"=>$brand->total,
                        "filter_value_id"=>$brand->brand_id,
                        "value"=>$brand->name,


                    ];
            }),

                'categories'=> $categories->map(function($category){
                        return [
                            "category_id"=>$category->category_id,
                            "name"=>$category->name,
                            "total"=>$category->total,
                            "filter_value_id"=>$category->category_id,
                            "value"=>$category->name,


                        ];
                }),
                'ingredients'=>$ingredients,
                // "banner"=>"https://cdn.beautybooth.qa/uploads/all/kyulTzzf4v9UWbSVnMwAovF3kBbhk4vHaoApqNRL.png"

            ]
        ]);
    }

     public function offerSliderList()
    {
        $desktop_slider_images = [];
        $mobile_slider_images = [];

        if (json_decode(get_setting('offer_slider_images'), true) != null) {
            $links = json_decode(get_setting('offer_slider_links'), true);
            $desktop_slider_images = collect(json_decode(get_setting('offer_slider_images'), true))->map(function ($data, $index) use ($links) {
                return [
                    'photo' => api_asset($data),
                    'url' => $links[$index] == null ? "/" : $links[$index]
                ];
            });
        }
        if (json_decode(get_setting('offer_mobile_slider_images'), true) != null) {
            $links = json_decode(get_setting('offer_mobile_slider_links'), true);
            $mobile_slider_images = collect(json_decode(get_setting('offer_mobile_slider_images'), true))->map(function ($data, $index) use ($links) {
                return [
                    'photo' => api_asset($data),
                    'url' => $links[$index] == null ? "/" : $links[$index]
                ];
            });
        }
        $product_ids = CampaignProduct::all()->pluck('product_id')->toArray();
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
        return response()->json([
            'data' => [
                'concerns'=>$concerns,
                'categories'=> $categories,
                'ingredients'=>$ingredients,
                'desktop' => $desktop_slider_images,
                'mobile' => $mobile_slider_images
            ]
        ]);
    }

     public function newData()
    {

        $product_ids = Product::where('created_at', '>=',Carbon::now()->subdays(180))->get()->pluck('id')->toArray();
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
        $brands = DB::table('products')->whereIn('products.id', $product_ids)->where('published', 1)
        ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
        ->select('brand_id', DB::raw('count(*) as total'), 'brands.name')
        ->groupBy('brand_id','brands.name')
        ->orderBy('total', 'desc')
        ->limit(10)
        ->get();
        return response()->json([
            'data' => [
                'banner'=> [
                    "desktop"=>
                        [
                            "photo"=>str_replace("admin.","cdn.",uploaded_asset(get_setting('new_desktop')))
                        
                        ],
                        "mobile"=>
                            [
                                "photo"=>str_replace("admin.","cdn.",uploaded_asset(get_setting('new_mobile')))
                            ]
                        
                ], 
                'concerns'=>$concerns,
                'categories'=> $categories->map(function($category){
                    return [
                        "category_id"=>$category->category_id,
                        "name"=>$category->name,
                        "total"=>$category->total,
                        "filter_value_id"=>$category->category_id,
                        "value"=>$category->name,


                    ];
            }),
                'ingredients'=>$ingredients,
                'brands'=> $brands->map(function($brand){
                    return [
                        "brand_id"=>$brand->brand_id,
                        "name"=>$brand->name,
                        "total"=>$brand->total,
                        "filter_value_id"=>$brand->brand_id,
                        "value"=>$brand->name,


                    ];
            }),
            ]
        ]);
    }

       public function offerConcernList()
    {
        $filterValues = FilterValue::where('is_offer', 1)->get();
        return new ConcernCollection($filterValues);
    }

    public function brandList()
    {
        $brands = Brand::where('is_offer', 1)->paginate(8);
        return new BrandOfferCollection($brands);
    }
}
