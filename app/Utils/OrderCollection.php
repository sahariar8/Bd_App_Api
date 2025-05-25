<?php

namespace App\Utils;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {

                return [
                    'id' => $data->id,
                    'total' => $data->grand_total,
                    'raffle_code' => $data->raffle_code,

                    'coupon_discount' => $data->coupon_discount,
                    'payment_status' => $data->payment_status,
                    'subtotal' => $data->grand_total - $data->coupon_discount - $data->orderDetails->first()->shipping_cost,
                    'shipping' => $data->orderDetails->first()->shipping_cost,
                    'delivery_status' => $data->delivery_status,

                    'order_time' => $data->created_at->format('d M Y H:i'),

                    'shipping_address' => json_decode($data->shipping_address),
                    'products' => $data->orderDetails->map(function ($detail) {
                        if (!$detail->product) {

                            return [
                                'quantity' => $detail->quantity,
                                'price' => $detail->price,

                                'id' => 1,
                                'name' => "Product Deleted",
                                'brand' => "",
                                'thumbnail_image' => "https://www.abc.com/images/products/",
                                'slug' => "test",
                                'has_discount' => false,
                                'stroked_price' => 100,
                                'main_price' => 100,


                            ];
                        }
                        return [
                            'quantity' => $detail->quantity,
                            'price' => $detail->price,

                            'id' => $detail->product->id,
                            'name' => $detail->product->name,
                            'brand' => $detail->product->brand ? $detail->product->brand->name : '',
                            'thumbnail_image' => api_asset($detail->product->thumbnail_img),
                            'slug' => $detail->product->slug,
                            'has_discount' => home_base_price($detail->product, false) != home_discounted_base_price($detail->product, false),
                            'stroked_price' => home_base_price($detail->product, false),
                            'main_price' => home_discounted_base_price($detail->product, false),
                            'discount_end_time' => home_discounted_end_time($detail->product),
                            'links' => [
                                'details' => route('products.show', $detail->product->slug),
                            ]

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
