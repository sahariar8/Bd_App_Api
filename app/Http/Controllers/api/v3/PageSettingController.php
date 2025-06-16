<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PageSettingController extends Controller
{
     public function appData(){
     
        // $categories= new TrendCategoryCollection($trendingCategories);
        // if($menu){
            
           $response= Cache::remember('app_data',86400,function(){
            $menu = Cache::remember('shop_frontend_menu',86400,function(){
                return DB::table('page_settings')->where('name','Menu')->first();
            });
            $trendingCategories = Category::where('featured', 1)->take(5)->get();
            $categories = Category::query()->HierachicalCategoryMenu()->orderBy('level')->get()->toArray();
            // dd($categories);
            $result = [];
            $products=Product::where('published',1)->orderBy('num_of_sale', 'desc')->limit(6)->get();
            foreach ($categories as $category) {
                $result[$category['slug']] = $category['name'];
                if (count($category['sub_categories']) > 0) {
                    foreach ($category['sub_categories'] as $sub) {
                        $lvl1 = $category['name'] . " > " . $sub['name'];
                        $result[$sub['slug']] = $lvl1;
                        if (count($sub['sub_categories']) > 0) {
                            foreach ($sub['sub_categories'] as $last) {
                                $lvl2 = $lvl1 . " > " . $last['name'];
                                $result[$last['slug']] = $lvl2;
                            }
                        }
                    }
                }
            }
            $menu = json_decode($menu->value);
            return ['status'=> true,
            'hierarchical_category' => $result,
            'trending_products' => $products->map(function ($data) {
                $today = date('Y-m-d');

                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'brand'=>$data->brand?$data->brand->name:"",
                    'thumbnail_image' => api_asset($data->thumbnail_img),
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),

                    // 'stroked_price' => home_price_raw($data),
                    // 'main_price' => home_discounted_base_price($data),
                    'stroked_price' => home_base_price($data, false),
                    'main_price' => home_discounted_base_price($data, false),
                    'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                    'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                        strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,
                    'rating' => (float) $data->rating,
                    'sales' => (int) $data->num_of_sale,

                    'slug' => $data->slug,
                ];
            }),
            'menu'=>$menu,'trending_categories'=>$trendingCategories->map(function($data){
                return [
                    'title' => $data->name,
                    'image' => api_asset($data->getRawOriginal('icon')),
                    'url' => $data->slug,

                ];
            })];
        });
        return response()->json($response);
       
    }
}
