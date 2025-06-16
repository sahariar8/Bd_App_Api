<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BrandOfferCollection extends ResourceCollection
{
     public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                $now=strtotime(date('d-m-Y H:i:s'));
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'offer_title' => $data->offer_title,

                    'slug' => $data->slug,
                    'logo' => api_asset($data->logo),
                    'feature_icon' => api_asset($data->feature_icon),
                    'feature_banner' => api_asset($data->offer_logo),
                    'image_background'=>$data->image_background,
                    'bottom_background'=>$data->bottom_background,
                    'font_color'=>$data->font_color,
                    'featured' => Product::where('brand_id', $data->id)->where('published', 1)->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
 ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')
 ->groupBy('products.id','combo_type','thumbnail_img','discount_end_date','unit_price','discount','discount_type','name','slug','discount_start_date','brand_id','product_new_from','product_new_to')->orderBy('in_stock','desc')->orderBy('num_of_sale', 'desc')->take(6)->get()->map(function ($product) {
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
                            'stroked_price' => home_base_price($product, false),
                            'main_price' => home_discounted_base_price($product, false),
                            'discount_end_time' => home_discounted_end_time($product),
                            'is_new' => $product->product_new_from <= $today && $product->product_new_to >= $today,
                            'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
                                strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date,
                           
                        ];
                    }),
                ];
            })
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
