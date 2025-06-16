<?php

namespace App\Utils;

use Illuminate\Http\Resources\Json\ResourceCollection;


class TrendCategoryCollection extends ResourceCollection
{
    public function toArray($request)
    {
        $transformed = $this->collection->map(function ($data) {
        return [
            'title' => $data->name,
            'image' => api_asset($data->getRawOriginal('icon')),
            'url'   => $data->slug,
        ];
    });

    return [
        'data' => request()->has('slice') ? $transformed : array_chunk($transformed->toArray(), 2),
    ];
        // if (request()->has('slice')) {
        //     return [
        //         'data' => $this->collection->map(function ($data) {
        //             return [
        //                 'title' => $data->name,
        //                 'image' => api_asset($data->getRawOriginal('icon')),
        //                 'url' => $data->slug,

        //             ];
        //         })
        //     ];
        // } else {
            
        //     return [
        //         'data' => array_chunk($this->collection->map(function ($data) {
        //             return [
        //                 'title' => $data->name,
        //                 'image' => api_asset($data->getRawOriginal('icon')),
        //                 'url' => $data->slug,

        //             ];
        //         })->toArray(), 2)
        //     ];
        // }
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200
        ];
    }
}
