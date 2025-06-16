<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Http\Resources\ConcernResource;
use App\Models\Brand;
use App\Models\FilterValue;
use App\Models\Redirect;
use App\Utils\BrandCollection;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $brand_query = Brand::query();
        if ($request->name != "" || $request->name != null) {
            $brand_query->where('name', 'like', '%' . $request->name . '%');
        }
        return new BrandCollection($brand_query->paginate(10));
    }
    public function siteMap(Request $request)
    {
        $brands = Brand::select(['slug', 'updated_at'])->get();
        return response()->json($brands);
    }

    public function collectSiteMap(Request $request)
    {
        $brands = Sale::select(['slug', 'updated_at'])->get();
        return response()->json($brands);
    }


    public function show($slug)
    {
        //uploaded_asset($category->banner)
        // return Category::where('parent_id', Category::whereSlug($slug)->first()->id)->get();
        $brand = Brand::whereSlug($slug)->first();
        if (!$brand) {
            $redirect = Redirect::where('old_url', $slug)->first();
            if ($redirect) {
                return [
                    "data" => [],
                    "success" => false,
                    "redirect" => $redirect

                ];
            }
            return [
                "data" => [],
                "success" => false,
                "status" => 404,
                "message" => "Invalid Route"
            ];
        }
        return new BrandResource($brand);
    }
    public function concernShow($slug)
    {
        //uploaded_asset($category->banner)
        // return Category::where('parent_id', Category::whereSlug($slug)->first()->id)->get();
        $brand = FilterValue::whereSlug($slug)->first();

        if (!$brand) {
            return [
                "data" => [],
                "success" => false,
                "status" => 404,
                "message" => "Invalid Route"
            ];
        }
        return new ConcernResource($brand);
    }
    //
    public function showOffer($slug)
    {
        //uploaded_asset($category->banner)
        // return Category::where('parent_id', Category::whereSlug($slug)->first()->id)->get();
        $brand = Brand::whereSlug($slug)->first();
        $strtotime = strtotime(date('Y-m-d h:i:s'));
        $products = Product::where('brand_id', $brand->id)->where('published', 1)->where('discount_start_date', '<=', $strtotime)->where('discount_end_date', '>=', $strtotime)->paginate(10);


        return response()->json([
            "id" => $brand->id,
            "name" => $brand->name,
            "logo" => uploaded_asset($brand->logo),
            "slug" => $brand->slug,
            "products" => $products->map(function ($data) {
                $today = date('Y-m-d');
                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'brand' => $data->brand ? $data->brand->name : '',

                    'thumbnail_image' => api_asset($data->thumbnail_img),
                    'slug' => $data->slug,
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'stroked_price' => home_base_price($data, false),
                    'main_price' => home_discounted_base_price($data, false),
                    'discount_end_time' => home_discounted_end_time($data),
                    'image_background' => $data->image_background,
                    'bottom_background' => $data->bottom_background,
                    'font_color' => $data->font_color,
                    'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                    'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                        strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,
                    'links' => [
                        'details' => route('products.show', $data->slug),
                    ]
                ];
            })
        ]);
    }



    public function getAllBrands()
    {
        $data = Brand::whereIsPublished(1)->orderBy('name')->get();
        $brands = array();
        if (request()->has('algolia')) {
            foreach ($data as $brand) {
                $brands[$brand['slug']] = $brand['name'];
            }
            unset($data);
            return response()->json($brands, 200);
        }
        foreach ($data as $brand) {
            $brands[$brand->name[0]][] = [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'is_top_rated' => (bool) $brand->top_rated,
                'is_best_sell' => (bool) $brand->best_sell,
                'is_featured' => (bool) $brand->is_offer,
                'logo' => str_replace("admin", "cdn", uploaded_asset($brand->logo))
            ];
        }
        unset($data);
        return response()->json($brands, 200);
    }

    public function top()
    {
        return new BrandCollection(Brand::where('top', 1)->get());
    }

    public function bestBrands()
    {
        return response()->json(Brand::whereIn('id', json_decode(get_setting('top10_brands')))->get()->transform(function ($brand) {
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'logo' => uploaded_asset($brand->logo)
            ];
        }), 200);
    }
}
