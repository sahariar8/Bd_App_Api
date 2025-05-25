<?php

namespace App\Utils;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AddressCollection extends ResourceCollection
{
     public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {

                $location_available = false;
                $lat = 90.99;
                $lang = 180.99;

                if ($data->latitude || $data->longitude) {
                    $location_available = true;
                    $lat = floatval($data->latitude);
                    $lang = floatval($data->longitude);
                }

                return [
                    'id'      => (int) $data->id,
                    'user_id' => (int) $data->user_id,
                    'address' => $data->address,
                    'name' => $data->name,
                    'country' => $data->country,
                    'city' => $data->city,
                    'zone' => $data->zone,
                    'area' => $data->area,
                    'email' => $data->email,
                    'city_id' =>  $data->city_id,
                    'zone_id' =>  $data->zone_id,
                    'area_id' =>  $data->area_id,
                    'city_name' =>  $data->city_name,
                    'zone_name' =>  $data->zone_name,
                    'area_name' =>  $data->area_name,
                    'postal_code' => $data->postal_code,
                    'phone' => $data->phone,
                    'type' => $data->type,
                    'set_default' => (int) $data->set_default,
                    'location_available' => $location_available,
                    'lat' => $lat,
                    'lang' => $lang,
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
