<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductDetailCollection;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\Review;
use App\Utils\CategoryUtility;
use App\Utils\ProductCardCollection;
use App\Utils\ProductMiniCollection;
use App\Utils\ProductStockCollection;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function brand($id, Request $request)
    {
        $products = Product::where('brand_id', $id);
        if ($request->name != "" || $request->name != null) {
            $products = $products->where('name', 'like', '%' . $request->name . '%');
        }

        return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
    }

     public function category($id, Request $request)
    {
        $category_ids = CategoryUtility::children_ids($id);
        $category_ids[] = $id;

        $products = Product::whereIn('category_id', $category_ids);

        if ($request->name != "" || $request->name != null) {
            $products = $products->where('name', 'like', '%' . $request->name . '%');
        }
        $products->where('published', 1);
        return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
    }

     public function show($slug)
    {

        $product=Product::where('slug', $slug)->get();
        $redirect=Redirect::where('old_url',$slug)->first();
        if(count($product)>0 && !$redirect){
            return new ProductDetailCollection($product);
        }
        else{
            
            if($redirect){
                return [
                    "data"=>[],
                    "success"=>false,
                    "redirect"=>$redirect

                ];
            }
            // $product=Product::where('id', $slug)->get();
            if(count($product)>0){
                //if slug contain any capital letter
                if (strtolower($slug) !== $slug) {
                    return [
                        "data"=>[],
                        "success"=>false,
                        "redirect"=>[
                            "old_url"=>$slug,
                            "new_url"=>strtolower($slug),
                            "code"=>301
                        ]
    
                    ];
                }


                return new ProductDetailCollection($product);
            }
            else{
                $ids=DB::table('u_pos.transaction_sell_lines')->join('u_pos.transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
                ->selectRaw('sum(quantity) as qty,product_id')
                ->where('transactions.type', '=', 'sell')
                ->groupBy('product_id')
                ->orderByRaw('sum(quantity) desc')
                ->limit(22)
                ->pluck('product_id')->toArray();
                $data = Cache::remember('api_best_sell_2', 86400, function () use ($ids) {
                    $now=strtotime(date('d-m-Y H:i:s'));
                    return new ProductStockCollection(Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')->whereIn('vld.product_id', $ids)->orderBy('in_stock','desc')->limit(12)->get());
                });
                return [
                    "data"=>[],
                    "success"=>false,
                    "status"=>404,
                    "best_sell"=>$data

                ];
            }
        }
    }

     public function search(Request $request)
    {
        $category_ids = [];
        $brand_ids = [];

        if ($request->categories != null && $request->categories != "") {
            $category_ids = explode(',', $request->categories);
        }

        if ($request->brands != null && $request->brands != "") {
            $brand_ids = explode(',', $request->brands);
        }

        $sort_by = $request->sort_key;
        $name = $request->name;
        $min = $request->min;
        $max = $request->max;


        $products = Product::query();

        $products->where('published', 1);

        if (!empty($brand_ids)) {
            $products->whereIn('brand_id', $brand_ids);
        }

        if (!empty($category_ids)) {
            $n_cid = [];
            foreach ($category_ids as $cid) {
                $n_cid = array_merge($n_cid, CategoryUtility::children_ids($cid));
            }

            if (!empty($n_cid)) {
                $category_ids = array_merge($category_ids, $n_cid);
            }

            $products->whereIn('category_id', $category_ids);
        }

        if ($name != null && $name != "") {
            $products->where(function ($query) use ($name) {
                $query->whereRaw("MATCH(name,description) AGAINST(? IN NATURAL LANGUAGE MODE)", [$name]);
            });
        }

        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }

        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }

        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('unit_price', 'asc');
                break;

            case 'price_high_to_low':
                $products->orderBy('unit_price', 'desc');
                break;

            case 'new_arrival':
                $products->orderBy('created_at', 'desc');
                break;

            case 'popularity':
                $products->orderBy('num_of_sale', 'desc');
                break;

            case 'top_rated':
                $products->orderBy('rating', 'desc');
                break;

            default:
                $products->orderBy('created_at', 'desc');
                break;
        }

        return new ProductMiniCollection(filter_products($products)->paginate(10));
    }

     public function campaignProduct()
    {
        
        $now=strtotime(date('d-m-Y H:i:s'));
        $strtotime = strtotime(date('Y-m-d h:i:s'));
        $campaign =Campaign::first();
        // $campaign = Campaign::orderBy('created_at', 'desc')->first();
        if($campaign){
            $product_ids = CampaignProduct::where('campaign_id', $campaign->id)->get()->pluck('product_id')->toArray();
        }
        else{
            $product_ids =[];
        }
        // $products = Product::where('discount_start_date', '<=', $strtotime)->where('discount_end_date', '>=', $strtotime)->where('discount', '>', 0)->get();
        // $products = Product::where('discount_start_date', '<=', $strtotime)->where('discount_end_date', '>=', $strtotime)->where('discount', '>', 0)->whereIn('id', $product_ids)->get();
        $products = Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')
        ->groupBy('products.id','combo_type','thumbnail_img','discount_end_date','unit_price','discount','discount_type','name','slug','discount_start_date','brand_id','product_new_from','products.product_new_to')->whereIn('products.id', $product_ids)->get();
        return [
            'products' => $products->map(function ($data) {
                $today = date('Y-m-d');

                return [
                    'id' => $data->id,
                    'name' => $data->name,
                    'qty' => $data->qty_available,
                    'combo_type' => $data->combo_type,
                    'brand' => $data->brand ? $data->brand->name : "",
                    'brand_slug' => $data->brand ? $data->brand->slug : "",
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
            'app_end' => date('y-m-d', $campaign->end_date),

        ];
    }

    public function sectionProducts($section_name)
    {
        if ($section_name == null) {
            return [];
        }
        $data = [];
        switch ($section_name) {
            case 'new_arrival':
                $data = Cache::remember('api_products_new_arrival', 86400, function () {
                    $now=strtotime(date('d-m-Y H:i:s'));

                    return new ProductStockCollection(Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')
                    ->groupBy('products.id','combo_type','thumbnail_img','discount_end_date','product_new_from','product_new_to','brand_id','discount_start_date','unit_price','discount','discount_type','name','slug')->with(['stocks'])
                        ->where('products.created_at', '>=',Carbon::now()->subdays(180))
                        ->orderBy('in_stock','desc')
                        ->orderBy('id','asc')
                        // ->whereBetween('product_new_to', [date('Y-m-d'), $max_date])
                        ->limit(12)
                        ->get());
                });
                break;
            case 'best_selling':
                // $ids=DB::table('u_pos.transaction_sell_lines')->join('u_pos.transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
               if(request()->has('test')){
                $p=Product::leftJoin('u_pos.transaction_sell_lines','transaction_sell_lines.product_id','products.id')->join('u_pos.transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')->where('published', 1)->select('product_id') ->groupBy('product_id')
                ->orderByRaw('sum(quantity) desc')->limit(200)->get()->pluck('product_id')->toArray();
                return response()->json($p, 200);
               }
                $data = Cache::remember('api_products_best_sell', 86400, function () {
                    $ids=Product::leftJoin('u_pos.transaction_sell_lines','transaction_sell_lines.product_id','products.id')->join('u_pos.transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')->where('published', 1)->select('product_id') ->groupBy('product_id')
                    ->orderByRaw('sum(quantity) desc')->limit(10)->get()->pluck('product_id')->toArray();
                    $now=strtotime(date('d-m-Y H:i:s'));
                    return new ProductStockCollection(Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')->whereIn('products.id',$ids)->orderByRaw(DB::raw("FIELD(products.id, " . implode(',', $ids) . ")"))->get());
                });
                break;
                 case '50_off':
                    $data = Cache::remember('50_ofkf', 86400, function () {
                              $campaign =Campaign::find(6);
        $product_ids = CampaignProduct::where('campaign_id', $campaign->id)->get()->pluck('product_id')->toArray();
      
       
                        return new ProductCardCollection(Product::where('discount', '>', 0)->whereIn('id', $product_ids)->limit(12)->get());
                    });
                    break;
                    case 'mid':
                        $data = Cache::remember('midd', 86400, function () {
                                  $campaign =Campaign::find(11);
                                  if($campaign){
            $product_ids = CampaignProduct::where('campaign_id', $campaign->id)->get()->pluck('product_id')->toArray();

                                  }
                                  else{
            $product_ids = [];

                                  }
          
           
                            return new ProductCardCollection(Product::where('discount', '>', 0)->whereIn('id', $product_ids)->limit(12)->get());
                        });
                        break;
                        case 'top_search':
                    // $data = Cache::remember('1_1499', 86400, function () {
                    //     return new ProductCardCollection(Product::where('published', 1)->where('is_best_sell', 1)->where('unit_price','<','500')->limit(12)->get());
                    // });
                    $data = Cache::remember('top_searchh1', 86400, function () {
                        $now=strtotime(date('d-m-Y H:i:s'));
                        return new ProductStockCollection(Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')->havingRaw("sum(qty_available) > 0")->orderBy('search_count','desc')->limit(12)->get());
                    });
                    break;
                case '1_499':
                    // $data = Cache::remember('1_1499', 86400, function () {
                    //     return new ProductCardCollection(Product::where('published', 1)->where('is_best_sell', 1)->where('unit_price','<','500')->limit(12)->get());
                    // });
                    $data = Cache::remember('1__499', 86400, function () {
                        $now=strtotime(date('d-m-Y H:i:s'));
                        return new ProductStockCollection(Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')->where('unit_price','<','500')->limit(12)->get());
                    });
                    break;
                    case '5_999':
                        // $data = Cache::remember('1_5999', 86400, function () {
                        //     return new ProductCardCollection(Product::where('published', 1)->where('is_best_sell', 1)->where('unit_price','>','499')->where('unit_price','<','1000')->limit(12)->get());
                        // });
                        $data = Cache::remember('5__999', 86400, function () {
                            $now=strtotime(date('d-m-Y H:i:s'));
                            return new ProductStockCollection(Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')->where('unit_price','>','499')->where('unit_price','<','1000')->limit(12)->get());
                        });
                        break;
                        case '9_1499':
                            // $data = Cache::remember('1_91499', 86400, function () {
                            //     return new ProductCardCollection(Product::where('published', 1)->where('is_best_sell', 1)->where('unit_price','>','999')->where('unit_price','<','1500')->limit(12)->get());
                            // });
                            $data = Cache::remember('9__1499', 86400, function () {
                                $now=strtotime(date('d-m-Y H:i:s'));
                                return new ProductStockCollection(Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')->where('unit_price','>','999')->where('unit_price','<','1500')->limit(12)->get());
                            });
                            break;
            // case 'test':
            //     $now=strtotime(date('d-m-Y H:i:s'));
            //     $data=new ProductStockCollection(Product::leftJoin('u_pos.transaction_sell_lines','transaction_sell_lines.product_id','products.id')->join('u_pos.transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')->leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then products.unit_price-discount else  products.unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  products.discount_type,products.id,name,slug,products.unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')->orderByRaw('sum(quantity) desc')->orderBy('in_stock','desc')->get());
            //     break;
            case 'best_selling_app':
                $data = Cache::remember('api_products_best_sell_appp', 86400, function () {
                    return new ProductCardCollection(Product::where('published', 1)->where('is_best_sell', 1)->take(6)->get());
                });
                break;
            case 'best_selling_home':
                $ids=DB::table('u_pos.transaction_sell_lines')->join('u_pos.transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
                ->selectRaw('sum(quantity) as qty,product_id')
                ->where('transactions.type', '=', 'sell')
                ->groupBy('product_id')
                ->orderByRaw('sum(quantity) desc')
                ->limit(20)
                ->pluck('product_id')->toArray();
               
                $data = Cache::remember('api_products_best_sell_home88', 86400, function () use ($ids){
                    $now=strtotime(date('d-m-Y H:i:s'));
                    return new ProductStockCollection(Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')->groupBy('products.id')->whereIn('vld.product_id', $ids)->orderBy('in_stock','desc')->limit(12)->get());
                });
                break;
                case 'new_home':

                   
                    $data = Cache::remember('api_products_new_homee', 86400, function (){
                        $now=strtotime(date('d-m-Y H:i:s'));
                        return new ProductStockCollection(Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')
                        ->groupBy('products.id','product_new_to','combo_type','thumbnail_img','discount_end_date','unit_price','discount','discount_type','name','slug','discount_start_date','brand_id','product_new_from')->where('products.created_at', '>=',Carbon::now()->subdays(180))->orderBy('in_stock','desc')->limit(12)->get());
                    });
                    break;
            case 'offer':
                $data = Cache::remember('api_offer', 86400, function () {
                    $now=strtotime(date('d-m-Y H:i:s'));
                    return new ProductStockCollection(Product::leftJoin('u_pos.variation_location_details as vld', 'vld.product_id', '=', 'products.id')->where('published', 1)->selectRaw('combo_type,thumbnail_img, (case when(discount_end_date>'.$now.') then unit_price-discount else  unit_price end) as net_price,(case when(sum(qty_available)>0) then 1 else  0 end) as in_stock,  discount_type,products.id,name,slug,unit_price,discount,discount_start_date,discount_end_date,sum(qty_available) as qty_available,brand_id,product_new_from,product_new_to')
                    ->groupBy('products.id','combo_type','thumbnail_img','discount_end_date','unit_price','discount','discount_type','name','slug','discount_start_date','brand_id','product_new_from','product_new_to')->where('discount_type', '!=', null)->where('discount_end_date', '>=', strtotime(date('d-m-Y')))->orderBy(DB::raw('RAND()'))->limit(12)->get());
                }); 
                break;
            case 'latest_reviews':
                $data = Review::where('status', 1)->limit(6)->get();
                break;
            default:
                # code...
                break;
        }

        return response()->json($data, 200);
    }
}
