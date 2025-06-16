<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductCardCollection extends ResourceCollection
{
    public function toArray($request)
    {
        $today = date('Y-m-d');
        $best_ids = Cache::remember('best_ids_list', 3600, function () {
            //     $results = DB::select(DB::raw('select product_id from order_details
            //     group by product_id order by sum(quantity) desc limit 350
            // '));
        $results = DB::table('order_details')
                ->select('product_id')
                ->selectRaw('SUM(quantity) as total_quantity')
                ->groupBy('product_id')
                ->orderBy('total_quantity', 'desc')
                ->limit(350)
                ->get();


            // Convert the results to a collection
            $collection = collect($results);

            // Use pluck to get the product_id values
            $ids = $collection->pluck('product_id')->toArray();
            return $ids;
        });
        return [
            'data' => $this->collection->map(function ($data) use ($today, $best_ids) {
                $fiveDaysAgo = \Carbon\Carbon::now()->subDays(5)->toDateString();

                $hold = DB::table('orders')
                    ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                    ->where('order_details.product_id', $data->id)
                    ->where('orders.delivery_status', '!=', 'cancelled')
                    ->where('orders.sync', 0)
                    ->whereDate('order_details.created_at', '>=', $fiveDaysAgo)
                    ->sum('order_details.quantity');
                return [
                    'id' => $data->id,
                    'is_best_sell' => in_array($data->id, $best_ids),
                    'name' => $data->name,
                    'brand' => $data->brand ? $data->brand->name : '',
                    'brand_slug' => $data->brand ? $data->brand->slug : '',

                    'qty' => $data->qty_available - $hold,
                    'combo_type' => $data->combo_type,
                    'thumbnail_image' => api_asset($data->thumbnail_img),
                    'slug' => $data->slug,
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'stroked_price' => (int) home_base_price($data, false),
                    'main_price' => (int) home_discounted_base_price($data, false),
                    'discount_end_time' => home_discounted_end_time($data),
                    'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                    'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                        strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,

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
