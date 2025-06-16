<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardCollection;
use App\Http\Resources\ProductStockCollection;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\FreeGift;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
     public function getCartItem($user_id)
    {
        $free_items = Cart::where('temp_user_id', $user_id)->where('price', '<', 1)->pluck('id')->toArray();
        $shop_items_raw_data = Cart::where('temp_user_id', $user_id)->when(request()->has('ids'), function ($q) use ($free_items) {
            $ids = json_decode(request()->ids);
            // if(request()->has('test')){
            //     dd($ids );
            // }
            return $q->whereIn('id', $ids)
                ->orWhereIn('id', $free_items);
        })->get()->toArray();
        $currency_symbol = currency_symbol();
        $shop_items_data = array();
        foreach ($shop_items_raw_data as $shop_items_raw_data_item) {
            $product = Product::where('id', $shop_items_raw_data_item["product_id"])->first();
            if (!$product) {
                continue;
            }
            $shop_items_data_item["id"] = intval($shop_items_raw_data_item["id"]);
            $shop_items_data_item["owner_id"] = intval($shop_items_raw_data_item["owner_id"]);
            $shop_items_data_item["user_id"] = intval($shop_items_raw_data_item["user_id"]);
            $shop_items_data_item["product_id"] = intval($shop_items_raw_data_item["product_id"]);
            $shop_items_data_item["product_name"] = $product->name;
            $shop_items_data_item["name"] = $product->name;
            $shop_items_data_item["product_thumbnail_image"] = api_asset($product->thumbnail_img);
            $shop_items_data_item["thumbnail_image"] = api_asset($product->thumbnail_img);

            $shop_items_data_item["variation"] = $shop_items_raw_data_item["variation"];
            $shop_items_data_item["price"] = (float) $shop_items_raw_data_item["price"];
            $shop_items_data_item["currency_symbol"] = $currency_symbol;
            $shop_items_data_item["tax"] = (float) $shop_items_raw_data_item["tax"];
            $shop_items_data_item["shipping_cost"] = (float) $shop_items_raw_data_item["shipping_cost"];
            $shop_items_data_item["quantity"] = intval($shop_items_raw_data_item["quantity"]);
            $shop_items_data_item["lower_limit"] = intval($product->min_qty);
            $shop_items_data_item["stroked_price"] = home_base_price($product, false);
            $shop_items_data_item["main_price"] = home_discounted_base_price($product, false);
            $shop_items_data_item["has_discount"] = home_base_price($product, false) != home_discounted_base_price($product, false);
            if ($shop_items_raw_data_item['variation'] == 'brand' || $shop_items_raw_data_item['variation'] == 'cart') {
                $shop_items_data_item["upper_limit"] = intval($product->stocks->where('variant', '')->first()->qty);
            } else {
                $shop_items_data_item["upper_limit"] = intval($product->stocks->where('variant', $shop_items_raw_data_item['variation'])->first()->qty);
            }
            $shop_items_data_item["slug"] = $product->slug;

            $shop_items_data[] = $shop_items_data_item;
        }
        return response()->json(
            [
                'status' => true,
                'items' => $shop_items_data,
                "summary" => $this->getSummary($user_id)

            ]
        );
    }

     public function getSummary($user_id, $owner_id = null)
    {

        $items = Cart::where('temp_user_id', $user_id)->when(request()->has('ids'), function ($q) {
            $ids = json_decode(request('ids'));
            return $q->whereIn('id', $ids);
        })->get();


        if ($items->isEmpty()) {
            return [
                'sub_total' => 0.00,
                'tax' => 0.00,
                'shipping_cost' => 0.00,
                'discount' => 0.00,
                'grand_total' => 0.00,
                'grand_total_value' => 0.00,
                'coupon_code' => "",
                'coupon_applied' => false,
                // 'favourites'=>
                'recommend' => new ProductCardCollection(Product::where('published', 1)->where('is_best_sell', 1)->inRandomOrder()->limit(6)->get()),
            ];
        }

        $sum = 0.00;
        $shippingCost = 0;
        $subTotal = 0;
        $withoutOffer = 0;
        foreach ($items as $cartItem) {
            $product = Product::find($cartItem->product_id);
            if ($product) {
                if (home_base_price($product, false) == home_discounted_base_price($product, false)) {
                    $withoutOffer += $cartItem->price * $cartItem->quantity;
                }
            }
            $item_sum = 0;
            $item_sum += $cartItem->price * $cartItem->quantity;
            $subTotal += $cartItem->price * $cartItem->quantity;
            $item_sum += $cartItem->shipping_cost - $cartItem->discount;
            $sum += $item_sum;   //// 'grand_total' => $request->g
        }
        $sum += $shippingCost;

        $now = strtotime(date('d-m-Y H:i:s'));
        $products = Product::where('published', 1)->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')
            ->orderBy('in_stock', 'desc')->inRandomOrder()->limit(6)->get();
        $freeShiping = false;
        if ($items[0]->coupon_code) {
            $coupon = Coupon::where('code', $items[0]->coupon_code)->first();
            if ($coupon) {
                $freeShiping = $coupon->free_delivery ? true : false;
            }
        }

        return [
            'sub_total' => $subTotal,
            'tax' => $items->sum('tax'),
            'free_shiping' => $freeShiping,

            'shipping_cost' => $shippingCost,
            'discount' => round($items->sum('discount')),
            'grand_total' => $sum,
            'grand_total_value' => convert_price($sum),
            'coupon_code' => $items[0]->coupon_code,
            'coupon_applied' => $items[0]->coupon_applied == 1,
            "free" => $this->getAvailableFree($user_id),
            'without_offer' => $withoutOffer,
            "picked_for_you" => new ProductStockCollection($products),
        ];
    }


    public function getAvailableFree($user_id)
    {
        $items = Cart::where('temp_user_id', $user_id)->when(request()->has('ids'), function ($q) {
            $ids = json_decode(request('ids'));
            return $q->whereIn('id', $ids);
        })->get();

        $freeItems = [];
        $brands = [];
        $cartTotal = 0;
        $existItems = Cart::where('temp_user_id', $user_id)->where('is_free', 1)->get();
        // free gift limit
        // if ($existItems->count() == 1) {
        //     return [];
        // }
        $existTotal = 0;
        // foreach ($existItems as $existItem) {
        //     $freeGift = FreeGift::find($existItem->parent_id);
        //     if ($freeGift) {
        //         $existTotal += $freeGift->min_shopping;
        //     }
        // }
        foreach ($items as $item) {
            $product = Product::find($item->product_id);
            if (!$product) {
                continue;
            }
            if (!isset($brands[$product->brand_id])) {
                $brands[$product->brand_id] = $item->price * $item->quantity;
            } else {
                $brands[$product->brand_id] += $item->price * $item->quantity;
            }
            $cartTotal += $item->price * $item->quantity;
            if ($product->free_gifts()->count() > 0) {

                $freeItems[] = [
                    'product' => $product->free_gifts()->get()->map(function ($prod) {
                        return [
                            'id' => $prod->id,
                            'name' => $prod->name,
                            'name_ar' => $prod->getTranslation('name', 'ar_QA'),
                            //  $shop_items_data_item["product_name_ar"] = $product->getTranslation('name', 'ar_QA');
                            'brand' => $prod->brand ? $prod->brand->name : '',

                            'thumbnail_image' => api_asset($prod->thumbnail_img),
                            'slug' => $prod->slug,
                            'has_discount' => home_base_price($prod, false) != home_discounted_base_price($prod, false),
                            'stroked_price' => home_base_price($prod, false),
                            'main_price' => home_discounted_base_price($prod, false),
                            'discount_end_time' => home_discounted_end_time($prod),
                            'current_stock' => (int) $prod->stocks->first()->qty,
                            'links' => [
                                'details' => route('products.show', $prod->slug),
                            ]
                        ];
                    })->first(),
                    'type' => 'product',
                    'parent_id' => $item->id,
                ];
            }
        }
        $cartTotal -= $existTotal;
        //->where('max_shopping', '>=', $cartTotal)
        $freeGifts = FreeGift::where('min_shopping', '<=', $cartTotal)->get();
        foreach ($freeGifts as $freeGift) {

            if ($freeGift && !$freeGift->brand_id) {
                if (Cart::where('variation', 'cart')->where('parent_id', $freeGift->id)->where('temp_user_id', $user_id)->count() < 2) {
                    // if((int)$freeGift->gift->stocks->first()->qty>0){
                    if ($freeGift->gift && DB::connection('mysql2')->table('variation_location_details')->where('product_id', $freeGift->gift->id)->first()) {
                        if (DB::connection('mysql2')->table('variation_location_details')->where('product_id', $freeGift->gift->id)->sum("qty_available") > 0) {
                            $freeItems[] = [
                                'product' => [
                                    'id' => $freeGift->gift->id,
                                    'name' => $freeGift->gift->name,
                                    'name_ar' => $freeGift->gift->getTranslation('name', 'ar_QA'),
                                    'brand' => $freeGift->gift->brand ? $freeGift->gift->brand->name : '',

                                    'thumbnail_image' => api_asset($freeGift->gift->thumbnail_img),
                                    'slug' => $freeGift->gift->slug,
                                    'has_discount' => home_base_price($freeGift->gift, false) != home_discounted_base_price($freeGift->gift, false),
                                    'stroked_price' => home_base_price($freeGift->gift, false),
                                    'main_price' => home_discounted_base_price($freeGift->gift, false),
                                    'current_stock' => (int) $freeGift->gift->stocks->first()->qty,
                                    'discount_end_time' => home_discounted_end_time($freeGift->gift),
                                    'links' => [
                                        'details' => route('products.show', $freeGift->gift->slug),
                                    ]
                                ],
                                'type' => 'cart',
                                'parent_id' => $freeGift->id,
                            ];
                        }
                    }
                }
            }
        }

        foreach ($brands as $id => $price) {
            // 'parent_id' => $freeGift->id,
            // 'variation' => "brand",

            $freeGift = FreeGift::where('brand_id', $id)->where('min_shopping', '<=', $price)->first();

            if ($freeGift) {
                if (Cart::where('variation', 'brand')->where('parent_id', $freeGift->id)->where('temp_user_id', $user_id)->count() == 0) {

                    $freeItems[] = [
                        'product' => [
                            'id' => $freeGift->gift->id,
                            'name' => $freeGift->gift->name,
                            'name_ar' => $freeGift->gift->getTranslation('name', 'ar_QA'),
                            'brand' => $freeGift->gift->brand ? $freeGift->gift->brand->name : '',

                            'thumbnail_image' => api_asset($freeGift->gift->thumbnail_img),
                            'slug' => $freeGift->gift->slug,
                            'has_discount' => home_base_price($freeGift->gift, false) != home_discounted_base_price($freeGift->gift, false),
                            'stroked_price' => home_base_price($freeGift->gift, false),
                            'main_price' => home_discounted_base_price($freeGift->gift, false),
                            'current_stock' => (int) $freeGift->gift->stocks->first()->qty,

                            'discount_end_time' => home_discounted_end_time($freeGift->gift),
                            'links' => [
                                'details' => route('products.show', $freeGift->gift->slug),
                            ]
                        ],
                        'type' => 'brand',
                        'parent_id' => $freeGift->id,
                    ];
                }
            }
        }
        // return $freeItems;
        return collect($freeItems)->filter(function ($fitem) use ($items) {
            return collect($items)->filter(function ($it) use ($fitem) {
                return $it["product_id"] == $fitem["product"]["id"] && $it["price"] > 0;
            })->count() == 0;
        })->values()->all();
        // return array_filter($freeItems,function($fitem) use($items){
        //    return count(array_filter($items,function($it) use($fitem){
        //         return $it["product_id"]==$fitem["product_id"] && $it["price"] >0;
        //     }))>0;
        // });
    }
}
