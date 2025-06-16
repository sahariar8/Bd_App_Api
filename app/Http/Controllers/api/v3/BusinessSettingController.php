<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Utils\BusinessSettingCollection;
use Illuminate\Http\Request;

class BusinessSettingController extends Controller
{
     public function index()
    {
        return new BusinessSettingCollection(BusinessSetting::all());
    }
}
