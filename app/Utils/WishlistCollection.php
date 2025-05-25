<?php

namespace App\Utils;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class WishlistCollection  extends ResourceCollection
{
     public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {
        $today = date('Y-m-d');
        $current_stock=0;
                
        if (!is_null($data->product->combo_type)) {
            // if($data->stocks->first()){
                // $current_stock = (int)$data->stocks->first()->qty;
                //$data->combo_packs
            // }
            $min=0;
            foreach($data->product->combo_packs as $com){
                $st=DB::connection('mysql2')->table('variation_location_details')->where('product_id', $com->id)->first();
                if($st){
                    $qt=(int)DB::connection('mysql2')->table('variation_location_details')->where('product_id', $com->id)->sum('qty_available') ;
                    if($qt<$min || $min==0){
                        $min=$qt;
                    }
                }
                else{
                    $min=0;
                }
            }
            $current_stock=$min;

        }
       else if(DB::connection('mysql2')->table('variation_location_details')->where('product_id', $data->product->id)->first()){
            $current_stock=(int)DB::connection('mysql2')->table('variation_location_details')->where('product_id', $data->product->id)->sum('qty_available') ;
        }
                return[
                    'id' => (integer) $data->id,
                    'combo_type' => $data->product->combo_type,
                    'qty'=>$current_stock,
                    'product_id' => (integer) $data->product_id,
                    'name' => $data->product->name,
                    'brand' => $data->product->brand ? $data->product->brand->name : '',

                    'thumbnail_image' => api_asset($data->product->thumbnail_img),
                    'slug' => $data->product->slug,
                    'has_discount' => home_base_price($data->product, false) != home_discounted_base_price($data->product, false),
                    'stroked_price' => home_base_price($data->product, false),
                    'main_price' => home_discounted_base_price($data->product, false),
                    'discount_end_time' => home_discounted_end_time($data->product),
                    'is_new' => $data->product->product_new_from <= $today && $data->product->product_new_to >= $today,
                    'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->product->discount_start_date &&
                        strtotime(date('d-m-Y H:i:s')) <= $data->product->discount_end_date&&$data->product->discount,
                    'links' => [
                        'details' => route('products.show', $data->product->slug),
                    ]

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
