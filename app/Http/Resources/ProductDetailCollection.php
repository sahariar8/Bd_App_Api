<?php

namespace App\Http\Resources;

use App\Models\Attribute;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Cart;
use App\Models\Category;
use App\Models\FreeGift;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Review;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductDetailCollection extends ResourceCollection
{
    public function toArray($request)
    {

        $favourite = false;

        if (auth()->check()) {
            $wish = Wishlist::where('user_id', Auth::user()->id)->where('product_id', $this->collection[0]->id)->first();
            $favourite = !empty($wish);
        }
        $cartCount = 0;
        $cartId = 0;
        if (request()->cart_id) {
            $cart = Cart::where('temp_user_id', request()->cart_id)->where('product_id', $this->collection[0]->id)->first();
            $cartCount = !empty($cart) ? $cart->quantity : 0;
            $cartId = !empty($cart) ? $cart->id : 0;
        }

        return [
            'data' => $this->collection->map(function ($data) use ($favourite, $cartCount, $cartId) {
                $precision = 2;
                $calculable_price = home_discounted_base_price($data, false);
                $calculable_price = number_format($calculable_price, $precision, '.', '');
                $calculable_price = floatval($calculable_price);
                // $calculable_price = round($calculable_price, 2);
                $photo_paths = get_images_path($data->photos);

                $photos = [
                    [
                        'variant' => '',
                        'path' => api_asset($data->thumbnail_img),
                    ],
                ];

                if (!empty($photo_paths)) {
                    for ($i = 0; $i < count($photo_paths); $i++) {
                        if ($photo_paths[$i] != "") {
                            $item = array();
                            $item['variant'] = "";
                            $item['path'] = $photo_paths[$i];
                            array_push($photos, $item);
                        }
                    }
                }

                foreach ($data->stocks as $stockItem) {
                    if ($stockItem->image != null && $stockItem->image != "") {
                        $item = array();
                        $item['variant'] = $stockItem->variant;
                        $item['path'] = api_asset($stockItem->image);
                        $photos[] = $item;
                    }
                }

                $freeGift = FreeGift::where('gift_product_id', $data->id)->first();
                if ($freeGift) {
                    if ($freeGift->type == 'cart') {
                        $name = "Cart";
                        $slug = "/";
                    } else {
                        $name = $data->type == 'product' ? $data->product->name : $data->brand->name;
                        $slug = $data->type == 'product' ? $data->product->slug : $data->brand->slug;
                    }
                }
                $fiveDaysAgo = \Carbon\Carbon::now()->subDays(5)->toDateString();

                $hold = DB::table('orders')
                    ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                    ->where('order_details.product_id', $data->id)
                    ->where('orders.delivery_status', '!=', 'cancelled')
                    ->where('orders.sync', 0)
                    ->whereDate('order_details.created_at', '>=', $fiveDaysAgo)
                    ->sum('order_details.quantity');
                // !is_null($data->combo_type)
                $current_stock = 0;

                if (!is_null($data->combo_type)) {
                    // if($data->stocks->first()){
                    // $current_stock = (int)$data->stocks->first()->qty;
                    //$data->combo_packs
                    // }
                    $min = 0;
                    foreach ($data->combo_packs as $com) {
                        $st = DB::connection('mysql2')->table('variation_location_details')->where('product_id', $com->id)->first();
                        if ($st) {
                            $qt = (int) DB::connection('mysql2')->table('variation_location_details')->where('product_id', $com->id)->sum('qty_available');
                            if ($qt < $min || $min == 0) {
                                $min = $qt;
                            }
                        } else {
                            $min = 0;
                        }
                    }
                    $current_stock = $min;

                } else if (DB::connection('mysql2')->table('variation_location_details')->where('product_id', $data->id)->first()) {
                    $current_stock = (int) DB::connection('mysql2')->table('variation_location_details')->where('product_id', $data->id)->sum('qty_available');
                }

                
               
                if($current_stock<1){
                    if($data->disable_stock){
                        $current_stock=5;
                    }
                }
                // if(request()->has('test')){
                //     dd($data->disable_stock);
                // }
                $tree = [];
                $fcategory=ProductCategory::where('product_id', $data->id)->orderBy('category_id','desc')->get()->first();
                $firstCategory=null;
                if($fcategory){

                    $firstCategory = Category::find($fcategory->category_id);
                }
                $sids=[];
                

                    $cpids=$fcategory?$firstCategory->products->pluck('id')->toArray():[];
                   $ids= DB::connection('mysql2')->table('variation_location_details')->whereIn('product_id', $cpids)->where('qty_available','>',0)->pluck('product_id')->toArray();
                    // array_push($sids,);
                    foreach ($ids as $id) {
                        array_push($sids, $id);
                    }
                   
                
                if ($fcategory && $firstCategory->parent_id != 0) {
                    $p1 = Category::find($firstCategory->parent_id);
                    if ($p1->parent_id != 0) {
                        $p2 = Category::find($p1->parent_id);
                        if($p2->parent_id!=0){
                            $p3=Category::find($p2->parent_id);
                            array_push($tree,[
                                "name"=>$p3->name,
                                "slug"=>$p3->slug,
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
                if($fcategory){
                    array_push($tree, [
                        "name" => $firstCategory->name,
                        "slug" => $firstCategory->slug,
                    ]);
                }
                $campaignName = "";
                $now=strtotime(date('d-m-Y H:i:s'));
                $campaignProduct = CampaignProduct::where("product_id", $data->id)->latest()->first();
                if ($campaignProduct) {
                    $campaign = Campaign::where('id',$campaignProduct->campaign_id)->where('start_date','>',$now)->where('end_date','<',$now)->first();
                    if ($campaign) {

                        $campaignName = $campaign->title;
                    }
                }
                $today = date('Y-m-d');

                $best_ids=  Cache::remember('best_ids_list', 3600, function () {
                //     $results = DB::select(DB::raw('select product_id from order_details
                //     group by product_id order by sum(quantity) desc limit 350
                // '));

                $results = DB::select('
                            SELECT product_id, SUM(quantity) as total_quantity
                            FROM order_details
                            GROUP BY product_id
                            ORDER BY total_quantity DESC
                            LIMIT 350
                        ');

                
                // Convert the results to a collection
                $collection = collect($results);
                
                // Use pluck to get the product_id values
                $ids = $collection->pluck('product_id')->toArray();
                return $ids;
                
                   });
                $badges=[];
                if(in_array($data->id,$best_ids)){
                    $badges[]="Best Sell";
                }
                if($data->product_new_from <= $today && $data->product_new_to >= $today){
                    $badges[]="New";
                }
                if(strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date&&$data->discount){
                    $badges[]="Offer";
                }

                return [
                    'is_gift' => $freeGift != null ? true : false,
                    'child_category'=>$fcategory? $tree[count($tree)-1]["name"]:"",
                    'campaign' => $campaignName,
                    'badges'=>$badges,
                    'flag'=>"uploads/all/uMVyJ82DVoV3u4xx09GQK74yqu1JinwdpYj9J2pr.png",
                    'gift' => $freeGift != null ? [
                        'title' => $freeGift->title,
                        'type' => $freeGift->type,
                        'name' => $name,
                        'slug' => $slug,
                        'link' => $freeGift->link,

                        'banner' => api_asset($freeGift->gift->thumbnail_img),
                    ] : [],
                    'show_stock' => DB::table('campaign_products')->where('campaign_id',9)->where('product_id',$data->id)->count() == 1,
                    'id' => (int) $data->id,
                    'cart_count' => $cartCount,
                    'cart_id' => $cartId,
                    "tree" => $tree,
                    "sku" => $data->barcode,
                    'name' => $data->name,
                    "no_follow" => $data->no_follow,
                    "no_index" => $data->no_index,
                    'description' => $data->description ? $data->description : "",
                    'is_favourite' => $favourite,
                    'category' => $data->category ? $data->category->slug : "",
                    'recommend' => $firstCategory ? Product::whereIn('id',$sids)->take(6)->get()->map(function ($item) {
                        $today = date('Y-m-d');
                        return [
                            'id' => $item->id,
                            'combo_type' => $item->combo_type,
                            'name' => $item->name,
                            'qty' => (int) DB::connection('mysql2')->table('variation_location_details')->where('product_id', $item->id)->sum('qty_available'),
                            'brand' => $item->brand ? $item->brand->name : '',
                            'brand_slug' => $item->brand ? $item->brand->slug : '',

                            'is_new' => $item->product_new_from <= $today && $item->product_new_to >= $today,
                            'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $item->discount_start_date &&
                            strtotime(date('d-m-Y H:i:s')) <= $item->discount_end_date && $item->discount,

                            'thumbnail_image' => api_asset($item->thumbnail_img),
                            'slug' => $item->slug,
                            'has_discount' => home_base_price($item, false) != home_discounted_base_price($item, false),
                            'stroked_price' => home_base_price($item, false),
                            'main_price' => home_discounted_base_price($item, false),
                            'discount_end_time' => home_discounted_end_time($item),
                            'links' => [
                                'details' => route('products.show', $item->slug),
                            ],
                        ];
                    }) : [],
                    'variants' => Product::where('variant_code', '!=', null)->where('variant_code', '!=', "null")->where('published', 1)->where('variant_code', $data->variant_code)->get()->map(function ($item) use ($data) {
                        return [
                            'id' => $item->id,
                            'variant_value' => $item->variant_value,
                            'is_active' => $item->id == $data->id,
                            'slug' => $item->slug,
                            'thumbnail_image' => api_asset($item->thumbnail_img),

                        ];
                    }),
                    'is_combo' => !is_null($data->combo_type),
                    'description_title' =>$data->description_title??"Product Info",
                    'combo_products' => $data->combo_packs->map(function ($prod) {
                        return [
                            'thumbnail_image' => api_asset($prod->thumbnail_img),
                            'name' => $prod->name,
                            'slug' => $prod->slug,
                            'piece' => $prod->pivot->bundle_threshold,
                        ];
                    }),
                    // 'discount_end_time' => home_discounted_end_time($data),

                    'color_name' => $data->color_variant_name,
                    'color_value' => $data->color_variant_value,
                    'color_variations' => Product::where('published',1)->where('color_variant_code', '!=', NULL)->where('variant_value', '!=', NULL)->where('color_variant_code', '!=', "null")->where('color_variant_code', $data->color_variant_code)->get()->map(function ($item) {

                        return [
                            'id' => $item->id,
                            'name' => $item->color_variant_name,
                            'value' => $item->color_variant_value,

                            'slug' => $item->slug,
                            'main_price' => home_discounted_base_price($item, false),
                        ];
                    }),
                    'sizes' => Product::where('size_variant_code', '!=', null)->where('size_variant_code', '!=', "null")->where('size_variant_code', $data->size_variant_code)->get()->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'variant_value' => $item->variant_value,
                            'slug' => $item->slug,
                            'main_price' => home_discounted_base_price($item, false),
                        ];
                    }),
                    'variant_value' => $data->variant_value,
                    'brand' => $data->brand->name,
                    'brand_slug' => $data->brand ? $data->brand->slug : '',
                    'total_sale' => $data->num_of_sale,
                    'total_cart' => Cart::where('product_id', $data->id)->count(),
                    'added_by' => $data->added_by,
                    'seller_id' => $data->user ? $data->user->id : 0,
                    'meta_title' => $data->meta_title ? $data->meta_title : $data->name,
                    'meta_description' => $data->meta_description??"",

                    "meta_keywords" => $data->meta_keywords ? $data->meta_keywords : "",
                    "meta_canonical" => $data->meta_canonical,
                    // 'shop_id' => $data->added_by == 'admin' ? 0 : $data->user->shop->id,
                    // 'shop_name' => $data->added_by == 'admin' ? 'In House Product' : $data->user->shop->name,
                    // 'shop_logo' => $data->added_by == 'admin' ? api_asset(get_setting('header_logo')) : api_asset($data->user->shop->logo),
                    'photos' => $photos,
                    'thumbnail_image' => api_asset($data->thumbnail_img),
                    'og_image' => api_asset($data->og_img),

                    'tags' => explode(',', $data->tags),
                    'price_high_low' => (float) explode('-', home_discounted_base_price($data, false))[0] == (float) explode('-', home_discounted_price($data, false))[1] ? format_price((float) explode('-', home_discounted_price($data, false))[0]) : "From " . format_price((float) explode('-', home_discounted_price($data, false))[0]) . " to " . format_price((float) explode('-', home_discounted_price($data, false))[1]),
                    'choice_options' => $this->convertToChoiceOptions(json_decode($data->choice_options)),
                    'colors' => Product::where('published',1)->where('color_variant_code', '!=', NULL)->where('variant_value', '!=', NULL)->where('color_variant_code', '!=', "null")->where('color_variant_code', $data->color_variant_code)->get()->map(function ($item) {

                        return [
                            'id' => $item->id,
                            'name' => $item->color_variant_name,
                            'value' => $item->color_variant_value,

                            'slug' => $item->slug,
                            'main_price' => home_discounted_base_price($item, false),
                        ];
                    }),
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'stroked_price' => home_base_price($data, false),
                    'main_price' => home_discounted_base_price($data, false),
                    'calculable_price' => $calculable_price,
                    'currency_symbol' => currency_symbol(),
                    'current_stock' => $current_stock - $hold,
                    'unit' => $data->unit,
                    'rating' => (float) $data->rating,
                    'min_free'=>(int)get_setting('flat_rate_shipping_free'),
                    'rating_count' => (int) Review::where(['product_id' => $data->id])->where(['status' => 1])->count(),
                    'earn_point' => (float) $data->earn_point,
                    // 'description' => $data->description,
                    'slug' => $data->slug,
                    'discount_end_time' => home_discounted_end_time($data)=="-"?"":home_discounted_end_time($data),
                    'link' => route('products.show', $data->slug),
                    'more_from_brand' => $data->brand ? $data->brand->products->take(6)->map(function ($item) {
                        $today = date('Y-m-d');

                        return [
                            'id' => $item->id,
                            'combo_type' => $item->combo_type,
                            'name' => $item->name,
                            'qty' => (int) DB::connection('mysql2')->table('variation_location_details')->where('product_id', $item->id)->sum('qty_available'),
                            'brand' => $item->brand ? $item->brand->name : '',
                            'brand_slug' => $item->brand ? $item->brand->slug : '',
                            'is_new' => $item->product_new_from <= $today && $item->product_new_to >= $today,
                            'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $item->discount_start_date &&
                            strtotime(date('d-m-Y H:i:s')) <= $item->discount_end_date && $item->discount,
                            'thumbnail_image' => api_asset($item->thumbnail_img),
                            'slug' => $item->slug,
                            'has_discount' => home_base_price($item, false) != home_discounted_base_price($item, false),
                            'stroked_price' => home_base_price($item, false),
                            'main_price' => home_discounted_base_price($item, false),
                            'discount_end_time' => home_discounted_end_time($item),
                            'links' => [
                                'details' => route('products.show', $item->slug),
                            ],
                        ];
                    }) : [],
                ];
            }),
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200,
        ];
    }

    protected function convertToChoiceOptions($data)
    {
        $result = array();
        //        if($data) {
        foreach ($data as $key => $choice) {
            $item['name'] = $choice->attribute_id;
            $item['title'] = Attribute::find($choice->attribute_id)->name;
            $item['options'] = $choice->values;
            array_push($result, $item);
        }
        //        }
        return $result;
    }

    protected function convertPhotos($data)
    {
        $result = array();
        foreach ($data as $key => $item) {
            array_push($result, api_asset($item));
        }
        return $result;
    }
}
