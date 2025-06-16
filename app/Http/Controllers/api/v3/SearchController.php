<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function ajax_search(Request $request)
    {

        $keywords = [];
        $products = Product::search($request->search)->get();
        foreach ($products as $key => $product) {
            foreach (explode(',', $product->tags) as $key => $tag) {
                if (stripos($tag, $request->search) !== false) {
                    if (sizeof($keywords) > 5) {
                        break;
                    } else {
                        if (!in_array(strtolower($tag), $keywords)) {
                            array_push($keywords, strtolower($tag));
                        }
                    }
                }
            }
        }
        $categories = Category::where('name', 'like', '%' . $request->search . '%')->limit(3)->get();
        if (count($products) > 0) {
            $shops = [];
            return response()->json([
                'status' => 'no_results',
                'message' => 'No products found.',
                'products' => [],
                'categories' => $categories,
                'keywords' => $keywords,
                'shops' => []
            ], 204);
        }

        return response()->json([
            'status' => 'no_results',
            'message' => 'No products found.',
            'products' => [],
            'categories' => $categories,
            'keywords' => $keywords,
            'shops' => []
        ], 204);
    }
}
