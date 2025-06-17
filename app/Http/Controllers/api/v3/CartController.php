<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardCollection;
use App\Http\Resources\ProductStockCollection;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\FreeGift;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function getCartItem($user_id)
    {
        // dd($user_id);
        $free_items = Cart::where('temp_user_id', $user_id)->where('price', '<', 1)->pluck('id')->toArray();
        // dd($free_items,'free_items');
        $shop_items_raw_data = Cart::where('temp_user_id', $user_id)->when(request()->has('ids'), function ($q) use ($free_items) {
            $ids = json_decode(request()->ids);
            // if(request()->has('test')){
            //     dd($ids );
            // }
            return $q->whereIn('id', $ids)
                ->orWhereIn('id', $free_items);
        })->get()->toArray();
        // dd($shop_items_raw_data, 'shop_items_raw_data');
        $currency_symbol = currency_symbol();
        $shop_items_data = array();
        foreach ($shop_items_raw_data as $shop_items_raw_data_item) {
            // dd($shop_items_raw_data_item, 'shop_items_raw_data_item');
            $product = Product::where('id', $shop_items_raw_data_item["product_id"])->first();
            if (!$product) {
                continue;
            }
            // dd($product, 'product');
            $shop_items_data_item["id"] = intval($shop_items_raw_data_item["id"]);
            $shop_items_data_item["owner_id"] = intval($shop_items_raw_data_item["owner_id"]);
            // dd($shop_items_data_item["owner_id"], 'owner_id');
            $shop_items_data_item["user_id"] = intval($shop_items_raw_data_item["user_id"]);
            $shop_items_data_item["product_id"] = intval($shop_items_raw_data_item["product_id"]);
            $shop_items_data_item["product_name"] = $product->name;
            $shop_items_data_item["name"] = $product->name;
            $shop_items_data_item["product_thumbnail_image"] = api_asset($product->thumbnail_img);
            // dd($product->thumbnail_img, 'thumbnail_img');
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

    public function getList($user_id)
    {
        // dd($user_id);
        $owner_ids = Cart::where('user_id', $user_id)->select('owner_id')->groupBy('owner_id')->pluck('owner_id')->toArray();
        // dd($owner_ids);
        $currency_symbol = currency_symbol();
        $shops = [];
        if (!empty($owner_ids)) {
            foreach ($owner_ids as $owner_id) {
                $shop = array();
                $shop_items_raw_data = Cart::where('user_id', $user_id)->where('owner_id', $owner_id)->get()->toArray();
                $shop_items_data = array();
                if (!empty($shop_items_raw_data)) {
                    // dd($shop_items_raw_data);
                    foreach ($shop_items_raw_data as $shop_items_raw_data_item) {
                        // dd($shop_items_raw_data_item,'sahariar');
                        $product = Product::where('id', $shop_items_raw_data_item["product_id"])->first();
                        $shop_items_data_item["id"] = intval($shop_items_raw_data_item["id"]);
                        $shop_items_data_item["owner_id"] = intval($shop_items_raw_data_item["owner_id"]);
                        $shop_items_data_item["user_id"] = intval($shop_items_raw_data_item["user_id"]);
                        $shop_items_data_item["product_id"] = intval($shop_items_raw_data_item["product_id"]);
                        $shop_items_data_item["product_name"] = $product->name;
                        $shop_items_data_item["product_thumbnail_image"] = api_asset($product->thumbnail_img);
                        $shop_items_data_item["variation"] = $shop_items_raw_data_item["variation"];
                        $shop_items_data_item["price"] = (float) $shop_items_raw_data_item["price"];
                        $shop_items_data_item["currency_symbol"] = $currency_symbol;
                        $shop_items_data_item["tax"] = (float) $shop_items_raw_data_item["tax"];
                        $shop_items_data_item["shipping_cost"] = (float) $shop_items_raw_data_item["shipping_cost"];
                        $shop_items_data_item["quantity"] = intval($shop_items_raw_data_item["quantity"]);
                        $shop_items_data_item["lower_limit"] = intval($product->min_qty);
                        $shop_items_data_item["upper_limit"] = intval($product->stocks->where('variant', $shop_items_raw_data_item['variation'])->first()->qty);
                        $shop_items_data_item["slug"] = $product->slug;

                        $shop_items_data[] = $shop_items_data_item;
                    }
                }


                $shop_data = Shop::where('user_id', $owner_id)->first();
                // dd($shop_data, 'shop_data');
                if ($shop_data) {
                    $shop['name'] = $shop_data->name;
                    $shop['owner_id'] = (int) $owner_id;
                    $shop['cart_items'] = $shop_items_data;
                } else {
                    $shop['name'] = "Inhouse";
                    $shop['owner_id'] = (int) $owner_id;
                    $shop['cart_items'] = $shop_items_data;
                }
                $shops[] = $shop;
            }
        }

        //dd($shops);

        return response()->json($shops);
    }

    public function add(Request $request, $user_id = null)
    {
        // dd($request->all(), $user_id);
        $product = Product::find($request->id);
        // dd($product, 'product');
        if (!$product) {
            $request->id = str_replace("'", "", $request->id);
            $product = Product::find(str_replace("'", "", $request->id));
        }
        $quantity = $request->quantity ? $request->quantity : 1;
        $variant = $request->variant;
        $tax = 0;

        if ($variant == '')
            $price = $product->unit_price;
        else {
            $product_stock = $product->stocks->where('variant', $variant)->first();
            $price = $product_stock->price;
        }

        //discount calculation based on flash deal and regular discount
        //calculation of taxes
        $discount_applicable = false;

        if ($product->discount_start_date == null) {
            $discount_applicable = true;
        } elseif (
            strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date
        ) {
            $discount_applicable = true;
        }

        if ($discount_applicable) {
            if ($product->discount_type == 'percent') {
                $price -= ($price * $product->discount) / 100;
            } elseif ($product->discount_type == 'amount') {
                $price -= $product->discount;
            }
        }

        foreach ($product->taxes as $product_tax) {
            if ($product_tax->tax_type == 'percent') {
                $tax += ($price * $product_tax->tax) / 100;
            } elseif ($product_tax->tax_type == 'amount') {
                $tax += $product_tax->tax;
            }
        }
        $adjust = false;
        if (!is_null($product->combo_type)) {
            $min = 50;
            foreach ($product->combo_packs as $com) {
                $st = DB::connection('mysql2')->table('variation_location_details')->where('product_id', $com->id)->first();
                if ($st) {
                    $qt = (int) DB::connection('mysql2')->table('variation_location_details')->where('product_id', $com->id)->sum('qty_available');
                    if ($qt < $min) {
                        $min = $qt;
                    }
                } else {
                    $min = 0;
                }
            }
            $stock = $min;
        } else if (DB::connection('mysql2')->table('variation_location_details')->where('product_id', $product->id)->first()) {
            $stock = (int) DB::connection('mysql2')->table('variation_location_details')->where('product_id', $product->id)->sum('qty_available');
            if ($product->disable_stock) {
                $stock = 5;
            }
        } else {
            $stock = 0;
        }

        if ($stock < $quantity) {

            $quantity = $stock;
            $adjust = true;
            // return response()->json(['result' => false, 'message' => "Minimum {$product->min_qty} item(s) should be ordered"], 200);
        }






        $variant_string = $variant != null && $variant != "" ? "for ($variant)" : "";
        $existingQuantity = 0;
        $cart = Cart::where('temp_user_id', $user_id)->where('product_id', $request->id)->where('variation', $variant)->first();
        if ($cart) {
            $existingQuantity = $cart->quantity;
            if ($product->max_qty > 0 && $product->max_qty < ($cart->quantity + 1)) {
                $now = strtotime(date('d-m-Y H:i:s'));

                $products = Product::where('published', 1)->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
                    ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')
                    ->orderBy('in_stock', 'desc')->inRandomOrder()->limit(6)->get();
                return response()->json([
                    'result' => false,
                    'message' => "Maximum quantity per order exeeded",
                    'items' => $this->getCartItemList($user_id),
                    "summary" => $this->getSummary($user_id),
                    'free' => $this->getAvailableFree($user_id),
                    "quantity" => $quantity,
                    // "id" => $cart->id,
                    "also_buy" => new ProductStockCollection($products),


                ]);
            }
        }

        if ($stock < ($quantity + $existingQuantity)) {
            if ($stock == 0) {
                return response()->json(['result' => false, 'message' => "Stock out"], 200);
            } else {
                $quantity = $stock;
                $adjust = true;
                // return response()->json(['result' => false, 'message' => "Only {$stock} item(s) are available {$variant_string}"], 200);
            }
        }
        $existFree = Cart::where('product_id', $request->id)->where('temp_user_id', $user_id)->where('is_free', 1)->first();

        if ($existFree) {
            return response()->json([
                'result' => true,
                'message' => "Already added as free gift",
                'items' => $this->getCartItemList($user_id),
                "summary" => $this->getSummary($user_id),
                'free' => $this->getAvailableFree($user_id),
                "quantity" => $quantity,
                "id" => $existFree->id,
                "also_buy" => new ProductStockCollection(Product::where('published', 1)->where('is_best_sell', 1)->inRandomOrder()->limit(6)->get()),

            ]);
        }



        if ($quantity > 0) {
            $cart = Cart::updateOrCreate([
                'user_id' => $request->user_id ? $request->user_id : null,
                'owner_id' => $product->user_id,
                'product_id' => $request->id,
                'variation' => $variant,
                'temp_user_id' => $user_id
            ], [
                'price' => $price,
                'tax' => $tax,
                'shipping_cost' => 0,
                'quantity' => $adjust ? $quantity : DB::raw("quantity + $quantity")
            ]);
        }

        $this->apply_coupon_code($request->user_id);
        // if (\App\Utility\NagadUtility::create_balance_reference($request->cost_matrix) == false) {
        //     return response()->json(['result' => false, 'message' => 'Cost matrix error']);
        // }


        $now = strtotime(date('d-m-Y H:i:s'));
        $products = Product::where('published', 1)->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')
            ->orderBy('in_stock', 'desc')->inRandomOrder()->limit(6)->get();

        return response()->json([
            'result' => true,
            'message' => $adjust ? "Adjusted to the maximum quantity" : 'Product added to cart successfully',
            'items' => $this->getCartItemList($user_id),
            "summary" => $this->getSummary($user_id),
            'free' => $this->getAvailableFree($user_id),
            "quantity" => $quantity,
            // "id" => $cart->id,
            "also_buy" => new ProductStockCollection($products),


        ]);
    }

    public function getCartItemList($user_id)
    {
        $freeGiftId = Cart::where('temp_user_id', $user_id)->where('price', '<', 1)->pluck('id')->toArray();
        $shop_items_raw_data = Cart::where('temp_user_id', $user_id)->when(request()->has('ids'), function ($q) use ($freeGiftId) {
            $ids = json_decode(request('ids'));
            return $q->whereIn('id', $ids)->orWhereIn('id', $freeGiftId);
        })->get()->toArray();
        $currency_symbol = currency_symbol();
        $shop_items_data = array();
        foreach ($shop_items_raw_data as $shop_items_raw_data_item) {

            $product = Product::where('id', $shop_items_raw_data_item["product_id"])->first();
            if ($product) {
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
                $shop_items_data_item["slug"] = $product->slug;
                $shop_items_data_item["brand"] = $product->brand->name;
                $shop_items_data_item["stroked_price"] = home_base_price($product, false);
                $shop_items_data_item["main_price"] = home_discounted_base_price($product, false);
                $shop_items_data_item["has_discount"] = home_base_price($product, false) != home_discounted_base_price($product, false);
                if ($shop_items_raw_data_item['variation'] == 'brand' || $shop_items_raw_data_item['variation'] == 'cart') {
                    $shop_items_data_item["upper_limit"] = intval($product->stocks->where('variant', '')->first()->qty);
                } else {
                    $shop_items_data_item["upper_limit"] = intval($product->stocks->where('variant', $shop_items_raw_data_item['variation'])->first()->qty);
                }
                $shop_items_data[] = $shop_items_data_item;
            } else {
                \Log::error("-------- Cart problem -----------" . $shop_items_raw_data_item["product_id"] . "  user id" . $user_id);
            }
        }
        return $shop_items_data;
    }

    public function apply_coupon_code($user_id)
    {
        $cart_items = Cart::where('temp_user_id', $user_id)->get();
        if (count($cart_items) == 0) {
            return;
        }
        $coupon_code = $cart_items[0]->coupon_code;
        if (empty($coupon_code)) {
            return;
        }
        $coupon = Coupon::where('code', $coupon_code)->first();

        if ($cart_items->isEmpty()) {
            return response()->json([
                'result' => false,
                'message' => 'Cart is empty'
            ], 200);
        }

        if ($coupon == null) {
            return response()->json([
                'result' => false,
                'message' => 'Invalid coupon code!'
            ], 200);
        }

        $in_range = strtotime(date('d-m-Y')) >= $coupon->start_date && strtotime(date('d-m-Y')) <= $coupon->end_date;

        if (!$in_range) {
            return response()->json([
                'result' => false,
                'message' => 'Coupon expired!'
            ], 200);
        }

        $is_used = CouponUsage::where('user_id', $user_id)->where('coupon_id', $coupon->id)->first() != null;

        // if ($is_used) {
        //     return response()->json([
        //         'result' => false,
        //         'message' => 'You already used this coupon!'
        //     ], 400);
        // }


        $coupon_details = json_decode($coupon->details);

        if ($coupon->type == 'cart_base') {
            $subtotal = 0;
            $tax = 0;
            $shipping = 0;
            $today = date('Y-m-d');
            foreach ($cart_items as $key => $cartItem) {
                $data = Product::where('id', $cartItem->product_id)->first();

                if ($coupon->offer_only) {

                    if (
                        strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                        strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount
                    ) {
                        $subtotal += $cartItem['price'] * $cartItem['quantity'];
                        Cart::where('id', $cartItem->id)->update([
                            'discount' => ($cartItem['price'] * $cartItem['quantity'] * $coupon->discount) / 100,
                            'coupon_code' => $coupon_code,
                            'coupon_applied' => 1
                        ]);
                    }
                } else {
                    //disable offer
                    if (

                        strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                        strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount
                    ) {
                        if ($coupon->id != 29) {

                            continue;
                        }
                    }
                    $subtotal += $cartItem['price'] * $cartItem['quantity'];
                }

                $tax += $cartItem['tax'] * $cartItem['quantity'];
                $shipping += $cartItem['shipping'] * $cartItem['quantity'];
            }
            $sum = $subtotal + $tax + $shipping;

            if (1) {

                if ($coupon->discount_type == 'percent') {
                    $coupon_discount = round(($sum * $coupon->discount) / 100);
                    if ($coupon_discount > $coupon_details->max_discount) {
                        $coupon_discount = $coupon_details->max_discount;
                    }
                } elseif ($coupon->discount_type == 'amount') {
                    $coupon_discount = $coupon->discount;
                }

                Cart::where('temp_user_id', $user_id)->update([
                    'discount' => DB::raw("ROUND((price * quantity * {$coupon->discount}) / 100)"),
                    'coupon_code' => $coupon_code,
                    'coupon_applied' => 1
                ]);
                if ($subtotal == 0) {
                    return response()->json([
                        'result' => false,
                        'message' => 'PLease buy offer product to avail the coupon'
                    ], 200);
                }
                // return response()->json([
                //     'result' => true,
                //     'message' => 'Coupon Applied',
                //     'cart' => $this->getCartItem($user_id),
                //     'subtotal' => $subtotal,
                //     'summary' => $this->getSummary($user_id),
                //     'free' => $this->getAvailableFree($user_id),

                // ]);
            } else {
                // return response()->json([
                //     'result' => false,
                //     'message' => 'Please spend BDT ' . $coupon_details->min_buy . ' to use this coupon'
                // ], 200);
            }
        } elseif ($coupon->type == 'product_base') {
            $coupon_discount = 0;
            foreach ($cart_items as $key => $cartItem) {
                foreach ($coupon_details as $key => $coupon_detail) {
                    if ($coupon_detail->product_id == $cartItem['product_id']) {
                        if ($coupon->discount_type == 'percent') {
                            $coupon_discount += $cartItem['price'] * $coupon->discount / 100;
                        } elseif ($coupon->discount_type == 'amount') {
                            $coupon_discount += $coupon->discount;
                        }
                    }
                }
            }


            Cart::where('temp_user_id', $user_id)->update([
                'discount' => $coupon_discount,
                'coupon_code' => $coupon_code,
                'coupon_applied' => 1
            ]);

            // return response()->json([
            //     'result' => true,
            //     'message' => 'Coupon Applied',
            //     'cart' => $this->getCartItem($request->user_id),
            //     'summary' => $this->getSummary($request->user_id),
            //     'free' => $this->getAvailableFree($request->user_id),


            // ]);
        }
    }

    public function removeAll($id)
    {
        $cart = Cart::where('temp_user_id', $id)->get();
        foreach ($cart as $item) {
            $item->delete();
        }
        return response()->json(['result' => true, 'cart' => [], 'message' => 'Cart updated'], 200);
    }
    public function destroy($id)
    {
        $cart = Cart::find($id);
        $user_id = $cart->temp_user_id;

        $parentCart = Cart::where('child_id', $cart->id)->first();
        if ($parentCart) {
            $parentCart->child_id = 0;
            $parentCart->save();
        }


        Cart::destroy($id);
        $this->apply_coupon_code($cart->temp_user_id);
        $this->updateFree($cart->temp_user_id);
        return response()->json([
            'result' => true,
            'cart' => $this->getCartItemList($cart->temp_user_id),
            'message' => 'Product is successfully removed from your cart',
            'items' => $this->getCartItemList($user_id),
            "summary" => $this->getSummary($cart->temp_user_id),
            "free" => $this->getAvailableFree($cart->temp_user_id),

        ], 200);
    }

    public function addFree(Request $request)
    {
       
        if ($request->type == 'product') {
           
            $cart = Cart::where('parent_id', $request->parent_id)->first();
            $product = Product::find($cart->product_id);
            $freeProduct = $product->free_gifts()->get()->first();

            if ($freeProduct) {
                $freeCart = Cart::updateOrCreate([
                    'user_id' => $cart->user_id,
                    'parent_id' => $cart->id,
                    'owner_id' => $product->user_id,
                    'product_id' => $freeProduct->id,
                    'variation' => "",
                    'temp_user_id' => $cart->temp_user_id,
                    'is_free' => true,
                ], [
                    'price' => 0,
                    'tax' => 0,
                    'shipping_cost' => 0,
                    'quantity' => 1
                ]);
                $cart->child_id = $freeCart->id;
                $cart->save();
            }
            $user_id = $cart->temp_user_id;
        } else if ($request->type == 'brand') {
            $carts = Cart::where('temp_user_id', $request->user_id)->get();
            $cart = $carts[0];
            $user_id = $cart->temp_user_id;
            $freeGift = FreeGift::find($request->parent_id);
            $sum = 0;
            foreach ($carts as $item) {
                $product = Product::find($item->product_id);
                if ($product->brand_id == $freeGift->brand_id) {
                    $sum += $item->price * $item->quantity;
                }
            }
            if ($sum >= $freeGift->min_shopping) {
                $freeCart = Cart::updateOrCreate([
                    'user_id' => $cart->user_id,
                    'parent_id' => $request->parent_id,
                    'variation' => "brand",
                    'owner_id' => $freeGift->user_id,
                    'product_id' => $freeGift->gift->id,
                    'temp_user_id' => $cart->temp_user_id,
                    'is_free' => true,
                ], [
                    'price' => 0,
                    'tax' => 0,
                    'shipping_cost' => 0,
                    'quantity' => 1
                ]);
                $cart->child_id = $freeCart->id;
                $cart->save();
            }
        } else {
            $carts = Cart::where('temp_user_id', $request->user_id)->get();
            $cart = $carts[0];
            $user_id = $cart->temp_user_id;
            $freeGift = FreeGift::find($request->parent_id);
            $sum = 0;
            foreach ($carts as $item) {
                $product = Product::find($item->product_id);

                $sum += $item->price * $item->quantity;
            }
            if ($sum >= $freeGift->min_shopping) {
                $freeProduct = Product::find($freeGift->gift->id);
                // $stock = $freeProduct->stocks->first()->qty;
                $stock = 0;
                if (DB::connection('mysql2')->table('variation_location_details')->where('product_id', $freeGift->gift->id)->first()) {
                    $stock = (int) DB::connection('mysql2')->table('variation_location_details')->where('product_id', $freeGift->gift->id)->sum("qty_available");
                }
                if ($stock < 1) {
                    return response()->json([
                        'result' => true,
                        'items' => $this->getCartItemList($user_id),
                        "summary" => $this->getSummary($user_id),
                        "free" => $this->getAvailableFree($user_id),
                        'message' => 'Out of stock'
                    ], 200);
                }
                Cart::where('temp_user_id', $request->user_id)->where('is_free', 1)->delete();
                $freeCart = Cart::updateOrCreate([
                    'user_id' => $cart->user_id,
                    'parent_id' => $request->parent_id,
                    'variation' => "cart",
                    'owner_id' => $freeGift->user_id,
                    'product_id' => $freeGift->gift->id,
                    'temp_user_id' => $cart->temp_user_id,
                    'is_free' => true,
                ], [
                    'price' => 0,
                    'tax' => 0,
                    'shipping_cost' => 0,
                    'quantity' => 1
                ]);
                $cart->child_id = $freeCart->id;
                $cart->save();
            }
        }


        return response()->json([
            'result' => true,
            'items' => $this->getCartItemList($user_id),
            "summary" => $this->getSummary($user_id),
            "free" => $this->getAvailableFree($user_id),
            'message' => 'Cart updated'
        ], 200);
    }

       public function increment(Request $request)
    {
        $cart = Cart::find($request->id);
        $quantity = $cart->quantity;
        if ($cart != null) {
            $product = Product::find($cart->product_id);
            if (!is_null($product->combo_type)) {
                $stock = (int) $product->stocks->first()->qty;
            } else if (DB::connection('mysql2')->table('variation_location_details')->where('product_id', $cart->product_id)->first()) {
                $stock = (int) DB::connection('mysql2')->table('variation_location_details')->where('product_id', $cart->product_id)->first()->qty_available;
            } else {
                $stock = 0;
            }
            if ($product->max_qty > 0 && $product->max_qty < ($cart->quantity + 1)) {
                return response()->json(['result' => false, 'message' => 'Maximum quantity per order exeeded'], 200);
            }
            if ($stock >= ($cart->quantity + 1) && $cart->price > 0) {
                $quantity++;
                $cart->update([
                    'quantity' => $cart->quantity + 1
                ]);
                $this->apply_coupon_code($cart->temp_user_id);

                return response()->json([
                    'result' => true,
                    'items' => $this->getCartItemList($cart->temp_user_id),
                    'message' => 'Cart updated',
                    'summary' => $this->getSummary($cart->temp_user_id),
                    'free' => $this->getAvailableFree($cart->temp_user_id),
                    "quantity" => $quantity
                ], 200);
            } else {
                return response()->json(['result' => false, 'message' => 'Maximum available quantity reached'], 200);
            }
        }

        return response()->json(['result' => false, 'message' => 'Something went wrong'], 200);
    }

    public function decrement(Request $request)
    {
        $cart = Cart::find($request->id);
        if ($cart != null) {

            $cart->update([
                'quantity' => $cart->quantity - 1
            ]);
            $user_id = $cart->temp_user_id;
            if ($cart->quantity == 0 || $cart->quantity < 0) {
                $cart->delete();
            }
            $this->apply_coupon_code($cart->temp_user_id);
            $this->updateFree($user_id);
            return response()->json([
                'result' => true,
                'items' => $this->getCartItemList($user_id),
                "summary" => $this->getSummary($user_id),
                "free" => $this->getAvailableFree($user_id),
                'message' => 'Cart updated',
                "quantity" => $cart->quantity
            ], 200);
        }

        return response()->json(['result' => false, 'message' => 'Something went wrong', 'refetch' => true], 200);
    }

     public function updateFree($user_id)
    {
        $allItems = Cart::where('temp_user_id', $user_id)->where('is_free', 0)->get();
        $items = Cart::where('temp_user_id', $user_id)->where('is_free', 1)->get();
        $existSum = 0;
        foreach ($items as $item) {
            if ($item->variation == "brand") {
                $freeGift = FreeGift::find($item->parent_id);
                $sum = 0;
                foreach ($allItems as $allItem) {
                    $product = Product::find($allItem->product_id);
                    if ($product->brand_id == $freeGift->brand_id) {
                        $sum += $allItem->quantity * $allItem->price;
                    }
                }
                if ($sum < $freeGift->min_shopping) {
                    $parentCart = Cart::where('child_id', $item->id)->first();
                    if ($parentCart) {
                        $parentCart->child_id = 0;
                        $parentCart->save();
                    }
                    $item->delete();
                } else {
                    $existSum += $sum;
                }
            } else if ($item->variation == "cart") {
                $freeGift = FreeGift::find($item->parent_id);
                if (!$freeGift) {
                    $item->delete();
                } else {
                    $sum = 0;
                    foreach ($allItems as $allItem) {
                        $product = Product::find($allItem->product_id);

                        $sum += $allItem->quantity * $allItem->price;
                    }
                    if (($sum - $existSum) < $freeGift->min_shopping) {

                        $parentCart = Cart::where('child_id', $item->id)->first();
                        if ($parentCart) {
                            $parentCart->child_id = 0;
                            $parentCart->save();
                        }
                        $item->delete();
                    } else {
                        $existSum += $sum;
                    }
                }
            } else {
                if (!Cart::find($item->parent_id)) {
                    $item->delete();
                }
            }
        }
    }
}
