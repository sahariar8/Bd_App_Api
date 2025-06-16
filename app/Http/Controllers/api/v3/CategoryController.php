<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Brand;
use App\Models\BusinessSetting;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Category;
use App\Models\FilterValue;
use App\Models\FreeGift;
use App\Models\GeneralSetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Redirect;
use App\Utils\AppBrandOfferCollection;
use App\Utils\AppConcernCollection;
use App\Utils\AppOfferCategoryDetailCollection;
use App\Utils\CategoryCollection;
use App\Utils\ConcernCollection;
use App\Utils\FreeGiftCollection;
use App\Utils\MobileSliderCollection;
use App\Utils\ProductCardCollection;
use App\Utils\ProductStockCollection;
use App\Utils\TrendCategoryCollection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index($parent_id = 0)
    {
        if (request()->has('parent_id') && is_numeric(request()->get('parent_id'))) {
            $parent_id = request()->get('parent_id');
        }
        return new CategoryCollection(Category::where('parent_id', $parent_id)->get());
    }
    public function siteMap(Request $request)
    {
        $categories = Category::without('category_translations')->select(['slug', 'updated_at'])->get();
        return response()->json($categories);
    }
    public function featured()
    {
        $categories = Category::where('featured', 1)->get();
        return new CategoryCollection($categories);
    }

    public function home()
    {
        $homepageCategories = BusinessSetting::where('type', 'home_categories')->first();
        $homepageCategories = json_decode($homepageCategories->value);
        return new CategoryCollection(Category::whereIn('id', $homepageCategories)->get());
    }
    public function trendingSlug($slug)
    {
        $brand = Brand::where('slug', $slug)->first();
        if ($brand) {
            return [
                "type" => "brand"
            ];
        } else {
            $category = Category::where('slug', $slug)->first();
            if ($category) {
                return [
                    "type" => "category"
                ];
            } else {
                $concern = FilterValue::where('slug', $slug)->first();
                if ($concern) {
                    return [
                        "type" => "concern"
                    ];
                }
                return [
                    "type" => "none"
                ];
            }
        }
    }
    public function trendingCategories()
    {
        $trendingCategories = Category::where('featured', 1)->limit(8)->get();
        return $trendingCategories->map(function ($data) {
            return [
                'title' => $data->name,
                'image' => api_asset($data->getRawOriginal('icon')),
                'url' => $data->slug,

            ];
        });
    }
    public function homeApp()
    {
        $trendingCategory = Category::where('featured', 1)->get();
        $trendingCategories = new TrendCategoryCollection($trendingCategory);
        $sliders = new MobileSliderCollection(json_decode(get_setting('mobile_slider_images'), true));
        $strtotime = strtotime(date('Y-m-d h:i:s'));
        $products = Product::where('discount_start_date', '<=', $strtotime)->where('discount_end_date', '>=', $strtotime)->take(8)->get();
        // dd($products);
        $campaign = Campaign::orderBy('created_at', 'desc')->first();
        // dd($campaign);
        $flashData = [
            'products' => $products->map(function ($data) {
                $today = date('Y-m-d');

                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'brand' => $data->brand ? $data->brand->name : "",
                    'thumbnail_image' => api_asset($data->thumbnail_img),
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'stroked_price' => home_base_price($data, false),
                    'main_price' => home_discounted_base_price($data, false),
                    'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                    'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                        strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,
                    'rating' => (float) $data->rating,
                    'sales' => (int) $data->num_of_sale,
                    'slug' => $data->slug,
                ];
            }),
            'end' => date('d-m-Y H:i:s', $campaign->end_date),
            'append' => date('Y-m-d', $campaign->end_date),

        ];

        // dd($flashData);
        $desktop_slider_images = [];
        $mobile_slider_images = [];

        if (json_decode(get_setting('offer_slider_images'), true) != null) {
            $links = json_decode(get_setting('offer_slider_links'), true);
            $desktop_slider_images = collect(json_decode(get_setting('offer_slider_images'), true))->map(function ($data, $index) use ($links) {
                return [
                    'photo' => api_asset($data),
                    'url' => $links[$index] == null ? "/" : $links[$index]
                ];
            });
        }
        if (json_decode(get_setting('offer_mobile_slider_images'), true) != null) {
            $links = json_decode(get_setting('offer_mobile_slider_links'), true);
            $mobile_slider_images = collect(json_decode(get_setting('offer_mobile_slider_images'), true))->map(function ($data, $index) use ($links) {
                return [
                    'photo' => api_asset($data),
                    'url' => $links[$index] == null ? "/" : $links[$index]
                ];
            });
        }
        $filterValues = FilterValue::where('is_offer', 1)->get();
        // dd($filterValues,'filter');
        $filterConcerns = new AppConcernCollection($filterValues);
        // dd($filterConcerns,'concerns');
        $freeGifts = FreeGift::whereHas('gift', function ($q) {

            $q->where('published', 1);
        })->latest()->get();
        //  dd($freeGifts,'free');
        $freeData = new FreeGiftCollection($freeGifts);
        //  dd($freeData,'freeData');

        $newArraival = Cache::remember('api_products_new_arrivals', 86400, function () {
            $min_date = Product::where('product_new_from', '<=', date('Y-m-d'))->min('product_new_from');
            $max_date = Product::where('product_new_to', '>=', date('Y-m-d'))->max('product_new_to');
            return new ProductCardCollection(Product::with(['stocks'])
                ->whereBetween('product_new_from', [$min_date, date('Y-m-d')])
                ->whereBetween('product_new_to', [date('Y-m-d'), $max_date])
                ->limit(12)
                ->get());
        });
        // dd($newArraival,'new');
        $bestSell = Cache::remember('api_products_best_sell_app', 86400, function () {
            return new ProductCardCollection(Product::where('published', 1)->where('is_best_sell', 1)->take(6)->get());
        });
        // dd($bestSell, 'best');
        $homepageCategories = BusinessSetting::where('type', 'home_categories')->first();
        // dd($homepageCategories, 'home');

        $homepageCategories = json_decode($homepageCategories->value);
        // dd($homepageCategories, 'homeCategories');

        $top_categories = Category::whereIn('id', $homepageCategories)->limit(20)->get()->map(function ($data) {
            return [
                'name' => $data->name,
                'banner' => api_asset($data->banner),
                'icon' => api_asset($data->icon),
                'slug' => $data->slug,
                'products' => $data->products->take(6)->map(function ($data) {
                    $today = date('Y-m-d');

                    return [
                        'id' => $data->id,
                        'name' => $data->name,
                        'brand' => $data->brand ? $data->brand->name : "",
                        'thumbnail_image' => api_asset($data->thumbnail_img),
                        'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),

                        // 'stroked_price' => home_price_raw($data),
                        // 'main_price' => home_discounted_base_price($data),
                        'stroked_price' => home_base_price($data, false),
                        'main_price' => home_discounted_base_price($data, false),
                        'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                        'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                            strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,
                        'rating' => (float) $data->rating,
                        'sales' => (int) $data->num_of_sale,

                        'slug' => $data->slug,
                    ];
                }),
            ];
        });
        // dd($top_categories, 'top');
        $gs = GeneralSetting::first();
        // dd($gs, 'gs');
        return response([
            "data" => [
                "force_version" => $gs->force_version,
                "play_link" => $gs->play_link,
                "trending_categories" => $trendingCategories,
                "sliders" => $sliders,
                "flash_data" => $flashData,
                'offer_slider' => [
                    'desktop' => $desktop_slider_images,
                    'mobile' => $mobile_slider_images
                ],
                "filter_concerns" => $filterConcerns,
                "free_gifts" => $freeData,
                "new_arraival" => $newArraival,
                "best_sell" => $bestSell,
                "top_categories" => $top_categories
            ]

        ]);
        // return response([
        //     "data" => Cache::remember('hodmde_app965', 60, function () use ($top_categories, $gs, $bestSell, $newArraival, $freeData, $filterConcerns, $trendingCategories, $sliders, $flashData, $desktop_slider_images, $mobile_slider_images) {
        //         return [
        //             "force_version" => $gs->force_version,
        //             "play_link" => $gs->play_link,
        //             "trending_categories" => $trendingCategories,
        //             "sliders" => $sliders,
        //             "flash_data" => $flashData,
        //             'offer_slider' => [
        //                 'desktop' => $desktop_slider_images,
        //                 'mobile' => $mobile_slider_images
        //             ],
        //             "filter_concerns" => $filterConcerns,
        //             "free_gifts" => $freeData,
        //             "new_arraival" => $newArraival,
        //             "best_sell" => $bestSell,
        //             "top_categories" => $top_categories
        //         ];
        //     }),
        // ]);
    }

    public function homeAppBd()
    {
        return response([
            "data" => Cache::remember('hosmsdddr_620M6b0', 3600, function () {
                $trendingCategory = Category::where('featured', 1)->get();
                $trendingCategories = new TrendCategoryCollection($trendingCategory);
                $sliders = new MobileSliderCollection(json_decode(get_setting('mobile_slider_images'), true));
                $now = time(); // More readable than using strtotime


                $products = Product::where('discount_start_date', '<=', $now)
                    ->where('discount_end_date', '>=', $now)
                    ->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
                    ->select([
                        'products.id',
                        'combo_type',
                        'thumbnail_img',
                        'discount_type',
                        'name',
                        'slug',
                        'unit_price',
                        'discount',
                        'discount_start_date',
                        'discount_end_date',
                        'brand_id',
                        'product_new_from',
                        'product_new_to',
                        DB::raw('SUM(qty_available) as qty_available'),
                        DB::raw("CASE WHEN SUM(qty_available) > 0 THEN 1 ELSE 0 END as in_stock"),
                        DB::raw("CASE WHEN discount_end_date > '{$now}' THEN unit_price - discount ELSE unit_price END as net_price")
                    ])
                    ->groupBy(
                        'products.id',
                        'combo_type',
                        'thumbnail_img',
                        'discount_type',
                        'name',
                        'slug',
                        'unit_price',
                        'discount',
                        'discount_start_date',
                        'discount_end_date',
                        'brand_id',
                        'product_new_from',
                        'product_new_to'
                    )
                    ->orderByDesc('in_stock')
                    ->take(8)
                    ->get();

                // dd($products,'sa');


                $campaign = Campaign::orderBy('created_at', 'desc')->first();
                $flashData = [
                    'products' => $products->map(function ($data) {
                        $today = date('Y-m-d');

                        return [
                            'id' => $data->id,
                            'in_stock' => $data->in_stock,
                            'name' => $data->name,
                            'name_ar' => $data->getTranslation('name', 'ar_QA'),

                            'brand' => $data->brand ? $data->brand->name : "",
                            'thumbnail_image' => api_asset($data->thumbnail_img),
                            'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),

                            // 'stroked_price' => home_price_raw($data),
                            // 'main_price' => home_discounted_base_price($data),
                            'stroked_price' => home_base_price($data, false),
                            'main_price' => home_discounted_base_price($data, false),
                            'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                            'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                                strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,
                            'rating' => (float) $data->rating,
                            'sales' => (int) $data->num_of_sale,

                            'slug' => $data->slug,
                        ];
                    }),
                    'end' => date('d-m-Y H:i:s', $campaign->end_date),
                    'append' => date('Y-m-d', $campaign->end_date),

                ];
                // dd($flashData,'flashData');
                $desktop_slider_images = [];
                $mobile_slider_images = [];
                $campaigns = Campaign::where('featured', 1)->get();


                $featureCampaigns = $campaigns->map(function ($campaign) {
                    $product_ids = CampaignProduct::where('campaign_id', $campaign->id)->take(10)->pluck('product_id')->toArray();
                    $products = Product::query();
                    $now = time();
                    $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
                        ->selectRaw('
                                    combo_type,
                                    thumbnail_img,
                                    (CASE WHEN discount_end_date > ' . $now . ' THEN unit_price - discount ELSE unit_price END) as net_price,
                                    (CASE WHEN SUM(qty_available) > 0 THEN 1 ELSE 0 END) as in_stock,
                                    discount_type,
                                    products.id,
                                    name,
                                    slug,
                                    unit_price,
                                    discount,
                                    discount_start_date,
                                    discount_end_date,
                                    SUM(qty_available) as qty_available,
                                    brand_id,
                                    product_new_from,
                                    product_new_to
                                ')
                        ->groupBy(
                            'products.id',
                            'combo_type',
                            'thumbnail_img',
                            'discount_type',
                            'name',
                            'slug',
                            'unit_price',
                            'discount',
                            'discount_start_date',
                            'discount_end_date',
                            'brand_id',
                            'product_new_from',
                            'product_new_to'
                        );

                    $products->whereIn('product_id', $product_ids);
                    return [
                        'title' => $campaign->title,
                        'slug' => $campaign->slug,

                        'banner' => [
                            "desktop" => str_replace("admin.", "cdn.", uploaded_asset($campaign->desktop_banner)),
                            "mobile" => str_replace("admin.", "cdn.", uploaded_asset($campaign->mobile_banner))
                        ],
                        'badge' => str_replace("admin.", "cdn.", uploaded_asset($campaign->badge)),
                        'background' => str_replace("admin.", "cdn.", uploaded_asset($campaign->background)),

                        'products' => new ProductStockCollection($products->get()),
                        'end' => date('Y-m-d H:i:s', $campaign->end_date) . ".000Z",

                    ];
                });
                // dd($featureCampaigns,'camp');
                if (json_decode(get_setting('offer_slider_images'), true) != null) {
                    $links = json_decode(get_setting('offer_slider_links'), true);
                    $desktop_slider_images = collect(json_decode(get_setting('offer_slider_images'), true))->map(function ($data, $index) use ($links) {
                        return [
                            'photo' => api_asset($data),
                            'url' => $links[$index] == null ? "/" : $links[$index]
                        ];
                    });
                }
                if (json_decode(get_setting('offer_mobile_slider_images'), true) != null) {
                    $links = json_decode(get_setting('offer_mobile_slider_links'), true);
                    $mobile_slider_images = collect(json_decode(get_setting('offer_mobile_slider_images'), true))->map(function ($data, $index) use ($links) {
                        return [
                            'photo' => api_asset($data),
                            'url' => $links[$index] == null ? "/" : $links[$index]
                        ];
                    });
                }
                $filterValues = FilterValue::where('is_offer', 1)->get();
                // dd($filterValues,'values');
                $filterConcerns = new ConcernCollection($filterValues);
                // dd($filterConcerns,'concerns');
                $freeGifts = FreeGift::whereHas('gift', function ($q) {

                    $q->where('published', 1);
                })->latest()->get();
                // dd($freeGifts,'gifts');
                $freeData = new FreeGiftCollection($freeGifts);
                // dd($freeData,'freeData');
                $newArraival = Cache::remember('api_products_new_arrivfa', 3600, function () {
                    $min_date = Product::where('product_new_from', '<=', date('Y-m-d'))->min('product_new_from');
                    // dd($min_date);
                    $max_date = Product::where('product_new_to', '>=', date('Y-m-d'))->max('product_new_to');
                    $strtotime = strtotime(date('Y-m-d H:i:s'));

                    return new ProductCardCollection(
                        Product::with(['stocks'])
                            ->whereBetween('product_new_from', [$min_date, date('Y-m-d')])
                            ->whereBetween('product_new_to', [date('Y-m-d'), $max_date])
                            ->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
                            ->selectRaw("
                combo_type,
                thumbnail_img,
                (CASE WHEN discount_end_date > {$strtotime} THEN unit_price - discount ELSE unit_price END) as net_price,
                (CASE WHEN SUM(qty_available) > 0 THEN 1 ELSE 0 END) as in_stock,
                discount_type,
                products.id,
                name,
                slug,
                unit_price,
                discount,
                discount_start_date,
                discount_end_date,
                SUM(qty_available) as qty_available,
                brand_id,
                product_new_from,
                product_new_to
            ")
                            ->groupBy(
                                'products.id',
                                'combo_type',
                                'thumbnail_img',
                                'discount_type',
                                'name',
                                'slug',
                                'unit_price',
                                'discount',
                                'discount_start_date',
                                'discount_end_date',
                                'brand_id',
                                'product_new_from',
                                'product_new_to'
                            )
                            ->orderBy('in_stock', 'desc')
                            ->limit(12)
                            ->get()
                    );
                });
                // dd($newArraival,'new');



                $bestSell = Cache::remember('api_products_best_s5ell_apffp', 3600, function () {
                    $strtotime = strtotime(date('Y-m-d H:i:s')); // Use 24-hour format
                    // $now = time();
                    // dd($strtotime,$now);

                    return new ProductCardCollection(
                        Product::where('published', 1)
                            ->where('is_best_sell', 1)
                            ->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
                            ->selectRaw("
            products.combo_type,
            products.thumbnail_img,
            (CASE WHEN products.discount_end_date > {$strtotime} THEN products.unit_price - products.discount ELSE products.unit_price END) as net_price,
            (CASE WHEN SUM(vld.qty_available) > 0 THEN 1 ELSE 0 END) as in_stock,
            products.discount_type,
            products.id,
            products.name,
            products.slug,
            products.unit_price,
            products.discount,
            products.discount_start_date,
            products.discount_end_date,
            SUM(vld.qty_available) as qty_available,
            products.brand_id,
            products.product_new_from,
            products.product_new_to
        ")
                            ->groupBy(
                                'products.id',
                                'products.combo_type',
                                'products.thumbnail_img',
                                'products.discount_type',
                                'products.name',
                                'products.slug',
                                'products.unit_price',
                                'products.discount',
                                'products.discount_start_date',
                                'products.discount_end_date',
                                'products.brand_id',
                                'products.product_new_from',
                                'products.product_new_to'
                            )
                            ->orderBy('in_stock', 'desc')
                            ->limit(6)
                            ->get()
                    );
                });

                // dd($bestSell,'bestSell');

                $gs = GeneralSetting::first();
                $homepageCategories = BusinessSetting::where('type', 'home_categories')->first();
                $homepageCategories = json_decode($homepageCategories->value);
                $top_categories = Category::whereIn('id', $homepageCategories)->limit(20)->get()->map(function ($data) {
                    return [
                        'name' => $data->name,
                        'name_ar' => $data->getTranslation('name', 'ar_QA'),
                        'icon' => api_asset($data->icon),
                        'slug' => $data->slug,

                    ];
                });

                // dd($top_categories,'top');
                $filterValues = FilterValue::where('is_offer', 1)->get();
                // dd($filterValues,'values');
                $brands = Brand::where('is_offer', 1)->orderBy('position')->paginate(8);

                return [
                    "force_version" => $gs->force_version,
                    "play_link" => $gs->play_link,
                    "ios_link" => "",
                    "ios_version" => 3,
                    "campaigns" => $featureCampaigns,
                    "trending_categories" => $trendingCategories,
                    "sliders" => $sliders,
                    "flash_data" => $flashData,
                    'offer_slider' => [
                        'desktop' => $desktop_slider_images,
                        'mobile' => $mobile_slider_images
                    ],
                    "filter_concerns" => $filterConcerns,
                    "free_gifts" => $freeData,
                    "feature_categories" => new AppOfferCategoryDetailCollection(Category::where('is_offer', true)->orderBy('position')->get()),
                    "feature_concerns" => new ConcernCollection($filterValues),
                    "feature_brands" => new AppBrandOfferCollection($brands),
                    "new_arraival" => $newArraival,
                    "best_sell" => $bestSell,
                    "top_categories" => [],
                    "trend_categories" => $top_categories
                ];
            }),
        ]);
    }




    public function campaignProduct(Request $request)
    {
        // $brand = Brand::where('slug', 'like', $slug)->first();
        // $campaign = Campaign::find(1);
        $product_ids = CampaignProduct::all()->pluck('product_id')->toArray();
        $products = Product::query();
        $sort_by = $request->sort_by;
        $now = strtotime(date('d-m-Y H:i:s'));
        $min = $request->min;
        $max = $request->max;
        // $brands = $request->brands;
        $concerns = $request->concerns;
        $categories = $request->categories;

        $ingredients = $request->ingredients;
        // $product_ids = ProductCategory::where('category_id', $category->id)->pluck('product_id');
        $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id');

        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }
        // if ($brands) {
        //     if (count(json_decode($brands)) != 0) {
        //         $products->whereIn('brand_id', json_decode($brands));
        //     }
        // }
        if ($categories) {
            if (count(json_decode($categories)) != 0) {
                $category_ids = DB::table('product_categories')

                    // ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('category_id', json_decode($categories))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                //   dd($category_ids);
                $products->whereIn('product_id', $category_ids);
            }
        }
        if ($concerns) {
            if (count(json_decode($concerns)) != 0) {
                $concern_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($concerns))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $concern_ids);
            }
        }
        if ($ingredients) {
            if (count(json_decode($ingredients)) != 0) {
                $ingredient_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($ingredients))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $ingredient_ids);
            }
        }
        $products->whereIn('product_id', $product_ids);
        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('in_stock', 'desc')->orderBy('unit_price');
                break;

            case 'price_high_to_low':
                $products->orderBy('in_stock', 'desc')->orderBy('net_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;

            case 'popularity':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'best_sale':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('in_stock', 'desc')->orderBy('rating', 'desc');
                break;

            default:
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;
        }


        return new ProductStockCollection($products->paginate(80));
    }
    public function saleProducts($slug, Request $request)
    {
        $sale = Sale::where('slug', $slug)->first();
        // $brand = Brand::where('slug', 'like', $slug)->first();
        // $campaign = Campaign::find(1);
        $product_ids = SaleProduct::where('sale_id', $sale->id)->get()->pluck('product_id')->toArray();
        $products = Product::query();
        $sort_by = $request->sort_by;
        $now = strtotime(date('d-m-Y H:i:s'));
        $min = $request->min;
        $max = $request->max;
        // $brands = $request->brands;
        $concerns = $request->concerns;
        $categories = $request->categories;

        $ingredients = $request->ingredients;
        // $product_ids = ProductCategory::where('category_id', $category->id)->pluck('product_id');
        $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id');

        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }
        // if ($brands) {
        //     if (count(json_decode($brands)) != 0) {
        //         $products->whereIn('brand_id', json_decode($brands));
        //     }
        // }
        if ($categories) {
            if (count(json_decode($categories)) != 0) {
                $category_ids = DB::table('product_categories')

                    // ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('category_id', json_decode($categories))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                //   dd($category_ids);
                $products->whereIn('product_id', $category_ids);
            }
        }
        if ($concerns) {
            if (count(json_decode($concerns)) != 0) {
                $concern_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($concerns))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $concern_ids);
            }
        }
        if ($ingredients) {
            if (count(json_decode($ingredients)) != 0) {
                $ingredient_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($ingredients))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $ingredient_ids);
            }
        }
        $products->whereIn('product_id', $product_ids);
        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('in_stock', 'desc')->orderBy('unit_price');
                break;

            case 'price_high_to_low':
                $products->orderBy('in_stock', 'desc')->orderBy('net_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;

            case 'popularity':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'best_sale':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('in_stock', 'desc')->orderBy('rating', 'desc');
                break;

            default:
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;
        }


        return new ProductStockCollection($products->get());
    }
    public function campaignProducts($slug, Request $request)
    {
        $campaign = Campaign::where('slug', $slug)->first();
        if (!$campaign) {
            return response()->json([
                "data" => []
            ]);
        }

        $product_ids = CampaignProduct::where('campaign_id', $campaign->id)->get()->pluck('product_id')->toArray();
        // $brand = Brand::where('slug', 'like', $slug)->first();
        // $campaign = Campaign::orderBy('created_at', 'desc')->first();
        // $product_ids = CampaignProduct::where('campaign_id', $campaign->id)->get()->pluck('product_id')->toArray();
        $products = Product::query();
        $sort_by = $request->sort_by;
        $now = strtotime(date('d-m-Y H:i:s'));
        $min = $request->min;
        $max = $request->max;
        $brands = $request->brands;
        $concerns = $request->concerns;
        $categories = $request->categories;
        $discount = $request->discount;
        $ingredients = $request->ingredients;
        if ($discount > 0) {
            // $products->where(DB::raw('discount/unit_price'), '>', $discount / 100);
            $products->where(DB::raw('discount/unit_price'), '>', $discount / 100)->where('discount_end_date', '>=', $now)->where('discount_start_date', '<=', $now);
        }
        // $product_ids = ProductCategory::where('category_id', $category->id)->pluck('product_id');
        $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id');
        $products->where('vld.qty_available', '>', 0);
        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }
        if ($brands) {
            if (count(json_decode($brands)) != 0) {
                $products->whereIn('brand_id', json_decode($brands));
            }
        }
        if ($categories) {
            if (count(json_decode($categories)) != 0) {
                $category_ids = DB::table('product_categories')

                    // ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('category_id', json_decode($categories))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                //   dd($category_ids);
                $products->whereIn('product_id', $category_ids);
            }
        }
        if ($concerns) {
            if (count(json_decode($concerns)) != 0) {
                $concern_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($concerns))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $concern_ids);
            }
        }
        if ($ingredients) {
            if (count(json_decode($ingredients)) != 0) {
                $ingredient_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($ingredients))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $ingredient_ids);
            }
        }
        $products->whereIn('products.id', $product_ids);

        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('in_stock', 'desc')->orderBy('unit_price');
                break;

            case 'price_high_to_low':
                $products->orderBy('in_stock', 'desc')->orderBy('net_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('in_stock', 'desc')->orderBy('products.serial', 'desc')->orderBy('products.created_at', 'desc');
                break;

            case 'popularity':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'best_sale':
                $products->orderBy('in_stock', 'desc')->orderBy('products.serial', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('in_stock', 'desc')->orderBy('rating', 'desc');
                break;

            default:
                $products->orderBy('in_stock', 'desc')->orderBy('products.serial', 'desc')->orderBy('products.created_at', 'desc');
                break;
        }


        return new ProductStockCollection($products->paginate(80));
    }
    public function bestProduct(Request $request)
    {
        // $brand = Brand::where('slug', 'like', $slug)->first();
        // $campaign = Campaign::orderBy('created_at', 'desc')->first();
        // $product_ids = CampaignProduct::where('campaign_id', $campaign->id)->get()->pluck('product_id')->toArray();
        $products = Product::query();
        $sort_by = $request->sort_by;
        $now = strtotime(date('d-m-Y H:i:s'));
        $min = $request->min;
        $max = $request->max;
        $brands = $request->brands;
        $concerns = $request->concerns;
        $categories = $request->categories;

        $ingredients = $request->ingredients;
        // $product_ids = ProductCategory::where('category_id', $category->id)->pluck('product_id');
        $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id','combo_type','thumbnail_img','discount_start_date','discount_end_date','unit_price','discount','discount_type','name','slug','brand_id','product_new_from','product_new_to');

        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }
        if ($brands) {
            if (count(json_decode($brands)) != 0) {
                $products->whereIn('brand_id', json_decode($brands));
            }
        }
        if ($categories) {
            if (count(json_decode($categories)) != 0) {
                $category_ids = DB::table('product_categories')

                    // ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('category_id', json_decode($categories))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                //   dd($category_ids);
                $products->whereIn('product_id', $category_ids);
            }
        }
        if ($concerns) {
            if (count(json_decode($concerns)) != 0) {
                $concern_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($concerns))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $concern_ids);
            }
        }
        if ($ingredients) {
            if (count(json_decode($ingredients)) != 0) {
                $ingredient_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($ingredients))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $ingredient_ids);
            }
        }
        $products->where('is_best_sell', 1);

        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('in_stock', 'desc')->orderBy('unit_price');
                break;

            case 'price_high_to_low':
                $products->orderBy('in_stock', 'desc')->orderBy('net_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;

            case 'popularity':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'best_sale':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('in_stock', 'desc')->orderBy('rating', 'desc');
                break;

            default:
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;
        }


        return new ProductStockCollection($products->paginate(40));
    }

    public function newProduct(Request $request)
    {
        // $brand = Brand::where('slug', 'like', $slug)->first();
        // $campaign = Campaign::orderBy('created_at', 'desc')->first();
        // $product_ids = CampaignProduct::where('campaign_id', $campaign->id)->get()->pluck('product_id')->toArray();
        $products = Product::query();
        $sort_by = $request->sort_by;
        $now = strtotime(date('d-m-Y H:i:s'));
        $min = $request->min;
        $max = $request->max;
        $brands = $request->brands;
        $concerns = $request->concerns;
        $categories = $request->categories;

        $ingredients = $request->ingredients;
        // $product_ids = ProductCategory::where('category_id', $category->id)->pluck('product_id');
        $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')
            ->groupBy('products.id','combo_type','thumbnail_img','discount_start_date','discount_end_date','unit_price','discount','discount_type','name','slug','brand_id','product_new_from','product_new_to');

        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }
        if ($brands) {
            if (count(json_decode($brands)) != 0) {
                $products->whereIn('brand_id', json_decode($brands));
            }
        }
        if ($categories) {
            if (count(json_decode($categories)) != 0) {
                $category_ids = DB::table('product_categories')

                    // ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('category_id', json_decode($categories))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                //   dd($category_ids);
                $products->whereIn('product_id', $category_ids);
            }
        }
        if ($concerns) {
            if (count(json_decode($concerns)) != 0) {
                $concern_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($concerns))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $concern_ids);
            }
        }
        if ($ingredients) {
            if (count(json_decode($ingredients)) != 0) {
                $ingredient_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($ingredients))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $ingredient_ids);
            }
        }
        $products->where('products.created_at', '>=', Carbon::now()->subdays(180));

        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('in_stock', 'desc')->orderBy('unit_price');
                break;

            case 'price_high_to_low':
                $products->orderBy('in_stock', 'desc')->orderBy('net_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;

            case 'popularity':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'best_sale':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('in_stock', 'desc')->orderBy('rating', 'desc');
                break;

            default:
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;
        }


        return new ProductStockCollection($products->paginate(40));
    }
    //concernProducts
    public function concernProducts($slug, Request $request)
    {
        $brand = FilterValue::where('slug', 'like', $slug)->first();
        $products = Product::query();
        $sort_by = $request->sort_by;
        $now = strtotime(date('d-m-Y H:i:s'));
        $min = $request->min;
        $max = $request->max;
        // $brands = $request->brands;
        $concerns = $request->concerns;
        $categories = $request->categories;

        $ingredients = $request->ingredients;

        // $product_ids = ProductCategory::where('category_id', $category->id)->pluck('product_id');
        $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')
            ->groupBy('products.id','combo_type','thumbnail_img','discount_start_date','discount_end_date','unit_price','discount','discount_type','name','slug','brand_id','product_new_from','product_new_to');

        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }
        // if ($brands) {
        //     if (count(json_decode($brands)) != 0) {
        //         $products->whereIn('brand_id', json_decode($brands));
        //     }
        // }
        if ($categories) {
            if (count(json_decode($categories)) != 0) {
                $category_ids = DB::table('product_categories')

                    // ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('category_id', json_decode($categories))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                //   dd($category_ids);
                $products->whereIn('product_id', $category_ids);
            }
        }
        if ($concerns) {
            if (count(json_decode($concerns)) != 0) {
                $concern_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($concerns))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $concern_ids);
            }
        }
        if ($ingredients) {
            if (count(json_decode($ingredients)) != 0) {
                $ingredient_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($ingredients))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $ingredient_ids);
            }
        }
        $ids = DB::table('product_filter_values')->where('filter_value_id', $brand->id)->select(['product_filter_id'])->pluck('product_filter_id')->toArray();
        $product_ids = DB::table('product_filters')->whereIn('id', $ids)->select(['product_id'])->pluck('product_id')->toArray();
        $products->whereIn('products.id', $product_ids);
        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('in_stock', 'desc')->orderBy('unit_price');
                break;

            case 'price_high_to_low':
                $products->orderBy('in_stock', 'desc')->orderBy('net_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;

            case 'popularity':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'best_sale':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('in_stock', 'desc')->orderBy('rating', 'desc');
                break;

            default:
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;
        }


        return new ProductStockCollection($products->paginate(80));
    }
    public function brandProducts($slug, Request $request)
    {
        $brand = Brand::where('slug', 'like', $slug)->first();
        $products = Product::query();
        $sort_by = $request->sort_by;
        $now = strtotime(date('d-m-Y H:i:s'));
        $min = $request->min;
        $max = $request->max;
        // $brands = $request->brands;
        $concerns = $request->concerns;
        $categories = $request->categories;

        $ingredients = $request->ingredients;

        // $product_ids = ProductCategory::where('category_id', $category->id)->pluck('product_id');
        $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,(case when(unit_price - (case when(discount_end_date>' . $now . ') then discount else 0 end) != unit_price) then true else false end) as is_offers,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')
            ->groupBy('products.id','combo_type','thumbnail_img','discount_start_date','discount_end_date','unit_price','discount','discount_type','name','slug','brand_id','product_new_from','product_new_to');

        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }
        // if ($brands) {
        //     if (count(json_decode($brands)) != 0) {
        //         $products->whereIn('brand_id', json_decode($brands));
        //     }
        // }
        if ($categories) {
            if (count(json_decode($categories)) != 0) {
                $category_ids = DB::table('product_categories')

                    // ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('category_id', json_decode($categories))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                //   dd($category_ids);
                $products->whereIn('product_id', $category_ids);
            }
        }
        if ($concerns) {
            if (count(json_decode($concerns)) != 0) {
                $concern_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($concerns))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $concern_ids);
            }
        }
        if ($ingredients) {
            if (count(json_decode($ingredients)) != 0) {
                $ingredient_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($ingredients))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $ingredient_ids);
            }
        }
        $products->where('brand_id', $brand->id);
        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('in_stock', 'desc')->orderBy('unit_price');
                break;

            case 'price_high_to_low':
                $products->orderBy('in_stock', 'desc')->orderBy('net_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('in_stock', 'desc')->orderBy('is_offers', 'desc')->orderBy('products.created_at', 'desc');
                break;

            case 'popularity':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'best_sale':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('in_stock', 'desc')->orderBy('rating', 'desc');
                break;

            default:
                $products->orderBy('in_stock', 'desc')->orderBy('is_offers', 'desc')->orderBy('products.created_at', 'desc');
                break;
        }


        return new ProductStockCollection($products->paginate(80));
    }
    public function trendingCategory()
    {
        if (request()->has('slice')) {
            $trendingCategories = Category::where('featured', 1)->limit(8)->get();
        } else {

            $trendingCategories = Category::where('featured', 1)->get();
        }

        return new TrendCategoryCollection($trendingCategories);
    }
    public function products($slug) {}
    public function show($slug)
    {

        //uploaded_asset($category->banner)
        // return Category::where('parent_id', Category::whereSlug($slug)->first()->id)->get();

        if (request()->has("test")) {
            dd($slug);
        }

        if ($slug == "body-care-dbjod") {
            $slug = "body-care";
        } else if ($slug == "makeup-tzoim") {
            $slug = "makeup";
        } else if ($slug == "fragrance-mzynx") {
            $slug = "fragrance";
        }
        $category = Category::where('slug', 'like', $slug)->first();
        // dd($category);
        if ($category) {
            // dd($category,'sa');
            $result = new CategoryResource($category);
            // dd($result,'result');
            return $result;
        } else {
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
    }

    //searchProducts
    public function searchProducts($slug)
    {

        //uploaded_asset($category->banner)
        // return Category::where('parent_id', Category::whereSlug($slug)->first()->id)->get();

        // ->having('relevance_score', '>', 15)

        $product_ids = Product::selectRaw(
            "id, MATCH(name, description) AGAINST(?) AS relevance_score",
            [$slug]
        )
            ->whereRaw("MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)", [$slug])
            ->having('relevance_score', '>', 5)
            ->pluck('id')
            ->toArray();

        $brands = DB::table('products')->whereIn('products.id', $product_ids)->where('published', 1)
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->select('brand_id', DB::raw('count(*) as total'), 'brands.name')
            ->groupBy('brand_id')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        $concerns = DB::table('product_filters')->whereIn('product_filters.product_id', $product_ids)
            ->where('product_filters.filter_id', 1)
            ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
            ->leftJoin('filter_values', 'filter_values.id', '=', 'product_filter_values.filter_value_id')
            ->select('filter_value_id', 'value', DB::raw('count(*) as total'))
            ->groupBy('value')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
        $ingredients = DB::table('product_filters')->whereIn('product_filters.product_id', $product_ids)
            ->where('product_filters.filter_id', 4)
            ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
            ->leftJoin('filter_values', 'filter_values.id', '=', 'product_filter_values.filter_value_id')
            ->select('filter_value_id', 'value', DB::raw('count(*) as total'))
            ->groupBy('value')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();


        $now = strtotime(date('d-m-Y H:i:s'));

        $result = [

            "brands" => $brands->map(function ($brand) {
                return [
                    "brand_id" => $brand->brand_id,
                    "total" => $brand->total,
                    "name" => $brand->name,
                    "value" => $brand->name,

                    "filter_value_id" => $brand->brand_id,

                ];
            }),
            "concerns" => $concerns,
            "ingredients" => $ingredients,

            'best_sell' => Product::whereIn('products.id', $product_ids)->where('published', 1)->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
                ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')->orderBy('in_stock', 'desc')->orderBy('is_best_sell', 'desc')->take(10)->get()->map(function ($data) {
                    $today = date('Y-m-d');
                    return [
                        'id' => $data->id,
                        'name' => $data->name,
                        'in_stock' => $data->in_stock,
                        'qty' => $data->qty_available,
                        'combo_type' => $data->combo_type,
                        'brand' => $data->brand ? $data->brand->name : '',
                        'brand_slug' => $data->brand ? $data->brand->slug : '',

                        'thumbnail_image' => api_asset($data->thumbnail_img),
                        'slug' => $data->slug,
                        'net_price' => $data->net_price,

                        'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                        'stroked_price' => home_base_price($data, false),
                        'main_price' => home_discounted_base_price($data, false),
                        'discount_end_time' => home_discounted_end_time($data),
                        'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                        'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                            strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,
                        'links' => [
                            'details' => route('products.show', $data->slug),
                        ]
                    ];
                })

        ];

        return $result;
    }
    public function topShow()
    {
        $product_ids = Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('products.id')->groupBy('products.id')->havingRaw("sum(qty_available) > 0")->get()->pluck('id');
        $brands = DB::table('products')->whereIn('products.id', $product_ids)->where('published', 1)
            ->leftJoin('brands', 'products.brand_id', '=', 'brands.id')
            ->select('brand_id', DB::raw('count(*) as total'), 'brands.name')
            ->groupBy('brand_id')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
        $concerns = DB::table('product_filters')->whereIn('product_filters.product_id', $product_ids)
            ->where('product_filters.filter_id', 1)
            ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
            ->leftJoin('filter_values', 'filter_values.id', '=', 'product_filter_values.filter_value_id')
            ->select('filter_value_id', 'value', DB::raw('count(*) as total'))
            ->groupBy('value')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
        $ingredients = DB::table('product_filters')->whereIn('product_filters.product_id', $product_ids)
            ->where('product_filters.filter_id', 4)
            ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
            ->leftJoin('filter_values', 'filter_values.id', '=', 'product_filter_values.filter_value_id')
            ->select('filter_value_id', 'value', DB::raw('count(*) as total'))
            ->groupBy('value')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();
        return [
            "data" => [
                "brands" => $brands,
                "concerns" => $concerns,
                "ingredients" => $ingredients,
            ]
        ];
    }
    public function topProducts(Request $request)
    {
        $products = Product::query();
        $sort_by = $request->sort_by;
        $min = $request->min;
        $max = $request->max;
        $brands = $request->brands;
        $concerns = $request->concerns;
        $ingredients = $request->ingredients;
        $now = strtotime(date('d-m-Y H:i:s'));
        $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id');

        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }
        if ($brands) {
            if (count(json_decode($brands)) != 0) {
                $products->whereIn('brand_id', json_decode($brands));
            }
        }
        if ($concerns) {
            if (count(json_decode($concerns)) != 0) {
                $concern_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($concerns))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $concern_ids);
            }
        }
        if ($ingredients) {
            if (count(json_decode($ingredients)) != 0) {
                $ingredient_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($ingredients))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('product_id', $ingredient_ids);
            }
        }
        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('in_stock', 'desc')->orderBy('unit_price');
                break;

            case 'price_high_to_low':
                $products->orderBy('in_stock', 'desc')->orderBy('net_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('in_stock', 'desc')->orderBy('products.created_at', 'desc');
                break;

            case 'popularity':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'best_sale':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('in_stock', 'desc')->orderBy('rating', 'desc');
                break;
            case 'top':
                $products->orderBy('in_stock', 'desc')->orderBy('search_count', 'desc');
                break;

            default:
                $products->orderBy('in_stock', 'desc')->orderBy('search_count', 'desc');
                break;
        }


        return new ProductStockCollection($products->paginate(80));
    }
    //searchProductsResult
    public function searchProductsResult($slug, Request $request)
    {
        $products = Product::query();
        $sort_by = $request->sort_by;
        $min = $request->min;
        $max = $request->max;
        $brands = $request->brands;
        $concerns = $request->concerns;
        $filters = $request->filters;

        $ingredients = $request->ingredients;
        $now = strtotime(date('d-m-Y H:i:s'));
        $product_ids = Product::whereRaw("MATCH(name, description) AGAINST(? IN BOOLEAN MODE)", [$slug])
            ->pluck('id')
            ->toArray();

        $now = now();

        $products = Product::query()
            ->whereIn('products.id', $product_ids)
            ->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw(
                "combo_type, 
        thumbnail_img, 
        (CASE WHEN discount_end_date > ? THEN unit_price - discount ELSE unit_price END) AS net_price, 
        (CASE WHEN SUM(qty_available) > 0 THEN 1 ELSE 0 END) AS in_stock, 
        discount_type,
        products.id,
        name,
        slug,
        unit_price,
        discount,
        discount_start_date,
        discount_end_date,
        SUM(qty_available) AS qty_available,
        brand_id,
        product_new_from,
        product_new_to,
        MATCH(name, description) AGAINST(?) AS relevance_score",
                [$now, $slug]
            )
            // ->having('relevance_score', '>',0.1)
            ->groupBy(
                'products.id',
                'combo_type',
                'thumbnail_img',
                'discount_type',
                'name',
                'slug',
                'unit_price',
                'discount',
                'discount_start_date',
                'discount_end_date',
                'brand_id',
                'product_new_from',
                'product_new_to',
                'description',
            );

        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }
        if ($brands) {
            if (count(json_decode($brands)) != 0) {
                $products->whereIn('brand_id', json_decode($brands));
            }
        }
        if ($concerns) {
            if (count(json_decode($concerns)) != 0) {
                $concern_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($concerns))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('products.id', $concern_ids);
            }
        }
        if ($filters) {
            if (count(json_decode($filters)) != 0) {
                $filter_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($filters))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('products.id', $filter_ids);
            }
        }
        if ($ingredients) {
            if (count(json_decode($ingredients)) != 0) {
                $ingredient_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($ingredients))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('products.id', $ingredient_ids);
            }
        }
        $products->whereIn('products.id', $product_ids);
        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('in_stock', 'desc')->orderBy('unit_price');
                break;

            case 'price_high_to_low':
                $products->orderBy('in_stock', 'desc')->orderBy('net_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('in_stock', 'desc')->orderBy('relevance_score', 'desc');
                break;

            case 'popularity':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'best_sale':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('in_stock', 'desc')->orderBy('rating', 'desc');
                break;

            default:
                $products->orderBy('in_stock', 'desc')->orderBy('relevance_score', 'desc');
                break;
        }


        return new ProductStockCollection($products->paginate(80));
    }

    public function categoryProducts($slug, Request $request)
    {
        // dd($slug);
        if ($slug == "body-care-dbjod") {
            $slug = "body-care";
        } else if ($slug == "makeup-tzoim") {
            $slug = "makeup";
        } else if ($slug == "fragrance-mzynx") {
            $slug = "fragrance";
        }
        $category = Category::where('slug', 'like', $slug)->first();
        $products = Product::query();
        $sort_by = $request->sort_by;
        $min = $request->min;
        $max = $request->max;
        $brands = $request->brands;
        $concerns = $request->concerns;
        $filters = $request->filters;

        $ingredients = $request->ingredients;
        $now = strtotime(date('d-m-Y H:i:s'));
        $product_ids = ProductCategory::where('category_id', $category->id)->pluck('product_id');
        // $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
        //     ->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>' . $now . ') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,(case when(unit_price - (case when(discount_end_date>' . $now . ') then discount else 0 end) != unit_price) then true else false end) as is_offers,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id');
        $products->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')
            ->selectRaw("
        combo_type,
        thumbnail_img,
        (CASE WHEN (discount_end_date > {$now}) THEN unit_price - discount ELSE unit_price END) AS net_price,
        (CASE WHEN (SUM(qty_available) > 0) THEN 1 ELSE 0 END) AS in_stock,
        (CASE WHEN (unit_price - (CASE WHEN (discount_end_date > {$now}) THEN discount ELSE 0 END) != unit_price) THEN true ELSE false END) AS is_offers,
        discount_type,
        products.id,
        name,
        slug,
        unit_price,
        discount,
        discount_start_date,
        discount_end_date,
        SUM(qty_available) AS qty_available,
        brand_id,
        product_new_from,
        product_new_to
    ")
            ->groupBy(
                'products.id',
                'combo_type',
                'thumbnail_img',
                'discount_type',
                'name',
                'slug',
                'unit_price',
                'discount',
                'discount_start_date',
                'discount_end_date',
                'brand_id',
                'product_new_from',
                'product_new_to'
            );


        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }
        if ($brands) {
            if (count(json_decode($brands)) != 0) {
                $products->whereIn('brand_id', json_decode($brands));
            }
        }
        if ($concerns) {
            if (count(json_decode($concerns)) != 0) {
                $concern_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($concerns))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('products.id', $concern_ids);
            }
        }
        if ($filters) {
            if (count(json_decode($filters)) != 0) {
                $filter_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($filters))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('products.id', $filter_ids);
            }
        }
        if ($ingredients) {
            if (count(json_decode($ingredients)) != 0) {
                $ingredient_ids = DB::table('product_filters')

                    ->leftJoin('product_filter_values', 'product_filter_values.product_filter_id', '=', 'product_filters.id')
                    ->whereIn('filter_value_id', json_decode($ingredients))
                    ->select('product_id')

                    ->pluck('product_id')->toArray();
                $products->whereIn('products.id', $ingredient_ids);
            }
        }
        $products->whereIn('products.id', $product_ids);
        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('in_stock', 'desc')->orderBy('unit_price');
                break;

            case 'price_high_to_low':
                $products->orderBy('in_stock', 'desc')->orderBy('net_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('in_stock', 'desc')->orderBy('products.serial', 'desc')->orderBy('is_offers', 'desc')->orderBy('products.created_at', 'desc');
                break;

            case 'popularity':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'best_sale':
                $products->orderBy('in_stock', 'desc')->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('in_stock', 'desc')->orderBy('rating', 'desc');
                break;

            default:
                $products->orderBy('in_stock', 'desc')->orderBy('products.serial', 'desc')->orderBy('is_offers', 'desc');
                break;
        }

        if ($request->has('test')) {
            dd($products->toSql(), $products->getBindings());
        }

        return new ProductStockCollection($products->paginate(80));
    }
    public function top()
    {
        $homepageCategories = BusinessSetting::where('type', 'home_categories')->first();

        $homepageCategories = json_decode($homepageCategories->value);

        return Category::whereIn('id', $homepageCategories)->limit(20)->get()->map(function ($data) {
            return [
                'name' => $data->name,
                'banner' => api_asset($data->banner),
                'icon' => api_asset($data->icon),
                'slug' => $data->slug,
                'products' => $data->products->take(6)->map(function ($data) {
                    $today = date('Y-m-d');

                    return [
                        'id' => $data->id,
                        'name' => $data->name,
                        'brand' => $data->brand ? $data->brand->name : "",
                        'thumbnail_image' => api_asset($data->thumbnail_img),
                        'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),

                        // 'stroked_price' => home_price_raw($data),
                        // 'main_price' => home_discounted_base_price($data),
                        'stroked_price' => home_base_price($data, false),
                        'main_price' => home_discounted_base_price($data, false),
                        'is_new' => $data->product_new_from <= $today && $data->product_new_to >= $today,
                        'is_offer' => strtotime(date('d-m-Y H:i:s')) >= $data->discount_start_date &&
                            strtotime(date('d-m-Y H:i:s')) <= $data->discount_end_date && $data->discount,
                        'rating' => (float) $data->rating,
                        'sales' => (int) $data->num_of_sale,

                        'slug' => $data->slug,
                    ];
                }),
            ];
        });
    }

    public function CategoryHierarchyIndexedBySlug()
    {
        $categories = Category::HierachicalCategoryMenu()->orderBy('level')->get()->toArray();
        $result = [];

        foreach ($categories as $category) {
            $result[$category['slug']] = $category['name'];
            if (count($category['sub_categories']) > 0) {
                foreach ($category['sub_categories'] as $sub) {
                    $lvl1 = $category['name'] . " > " . $sub['name'];
                    $result[$sub['slug']] = $lvl1;
                    if (count($sub['sub_categories']) > 0) {
                        foreach ($sub['sub_categories'] as $last) {
                            $lvl2 = $lvl1 . " > " . $last['name'];
                            $result[$last['slug']] = $lvl2;
                        }
                    }
                }
            }
        }

        return response()->json($result, 200);
    }
}
