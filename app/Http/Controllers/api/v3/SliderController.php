<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Http\Resources\MobileSliderCollection;
use Illuminate\Http\Request;

class SliderController extends Controller
{
     public function mobileSliders()
    {
        return new MobileSliderCollection(json_decode(get_setting('mobile_slider_images'), true));
    }
}
