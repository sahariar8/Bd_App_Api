<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MobileSliderCollection extends ResourceCollection
{
    public function toArray($request)
    {
        $links = json_decode(get_setting('mobile_slider_links'), true);
        return [
            'data' => $this->collection->map(function ($data, $index) use ($links) {
                return [
                    'photo' => api_asset($data),
                    'url' => $links[$index] == null ? "/" : $links[$index]
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
