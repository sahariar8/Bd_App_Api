<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Utils\CategoryCollection;
use Illuminate\Http\Request;

class SubCategoryController extends Controller
{
    public function index($id)
    {
        return new CategoryCollection(Category::where('parent_id', $id)->get());
    }
}
