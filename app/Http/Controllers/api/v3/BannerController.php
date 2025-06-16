<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Utils\BannerCollection;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
         return new BannerCollection(json_decode(get_setting('home_banner1_images'), true));
    }

}
