<?php

use App\Http\Controllers\api\v3\AuthController;
use App\Http\Controllers\api\v3\BannerController;
use App\Http\Controllers\api\v3\BrandController;
use App\Http\Controllers\api\v3\BusinessSettingController;
use App\Http\Controllers\api\v3\CartController;
use App\Http\Controllers\api\v3\CategoryController;
use App\Http\Controllers\api\v3\OfferController;
use App\Http\Controllers\api\v3\PageSettingController;
use App\Http\Controllers\api\v3\PasswordResetController;
use App\Http\Controllers\api\v3\ProductController;
use App\Http\Controllers\api\v3\ProfileController;
use App\Http\Controllers\api\v3\SearchController;
use App\Http\Controllers\api\v3\SliderController;
use App\Http\Controllers\api\v3\WishlistController;
use Illuminate\Support\Facades\Route;



Route::prefix('v3/auth')->group(function () {

    Route::post('register-login',[AuthController::class,'registerOrLoginViaOtp']);//done
    Route::post('register',[AuthController::class,'register'])->name('register');//done
    Route::post('verification-login', [AuthController::class,'verify_phone_login']);//done
    Route::post('login', [AuthController::class,'login'])->name('login');//done
    // Social-Login
    Route::post('social-login', [AuthController::class,'socialLogin']);

    //Forget Password
    Route::post('password/forget-request', [PasswordResetController::class,'forgetRequest'])->name('password.forget_request');//done
    Route::post('password/confirm-reset', [PasswordResetController::class,'confirmReset'])->name('password.confirm_reset');//done
    Route::post('password/resend-code', [PasswordResetController::class,'resendCode'])->name('password.resend_code');//done

    Route::middleware('auth:api')->group(function () {
        Route::get('user', [AuthController::class,'user'])->name('user');//done
        Route::get('user/all', [AuthController::class,'userAll'])->name('user.all');//done
        Route::post('logout', [AuthController::class,'logout'])->name('logout');//done


        // Profile

        Route::get('profile/counters/{user_id}', [ProfileController::class,'counters']);//done
        Route::get('profile/info', [ProfileController::class,'getInfo']);//done
        Route::post('profile/info', [ProfileController::class,'storeInfo']);//done
        Route::post('profile/update', [ProfileController::class,'update']);//done
        Route::post('profile/update-device-token', [ProfileController::class,'update_device_token']);//done
        Route::post('profile/update-password', [ProfileController::class,'updatePassword']);//done
        Route::post('profile/image-upload', [ProfileController::class,'imageUpload']);//done
    });
    
  
});

Route::prefix('v3')->group(function () {


    Route::post('/ajax-search', [SearchController::class,'ajax_search'])->middleware('api');

    // Route::prefix('delivery-boy')->group(function () {
    //     Route::get('dashboard-summary/{id}', 'Api\V2\DeliveryBoyController@dashboard_summary')->middleware('auth:api');
    //     Route::get('deliveries/completed/{id}', 'Api\V2\DeliveryBoyController@completed_delivery')->middleware('auth:api');
    //     Route::get('deliveries/cancelled/{id}', 'Api\V2\DeliveryBoyController@cancelled_delivery')->middleware('auth:api');
    //     Route::get('deliveries/on_the_way/{id}', 'Api\V2\DeliveryBoyController@on_the_way_delivery')->middleware('auth:api');
    //     Route::get('deliveries/picked_up/{id}', 'Api\V2\DeliveryBoyController@picked_up_delivery')->middleware('auth:api');
    //     Route::get('deliveries/assigned/{id}', 'Api\V2\DeliveryBoyController@assigned_delivery')->middleware('auth:api');
    //     Route::get('collection-summary/{id}', 'Api\V2\DeliveryBoyController@collection_summary')->middleware('auth:api');
    //     Route::get('earning-summary/{id}', 'Api\V2\DeliveryBoyController@earning_summary')->middleware('auth:api');
    //     Route::get('collection/{id}', 'Api\V2\DeliveryBoyController@collection')->middleware('auth:api');
    //     Route::get('earning/{id}', 'Api\V2\DeliveryBoyController@earning')->middleware('auth:api');
    //     Route::get('cancel-request/{id}', 'Api\V2\DeliveryBoyController@cancel_request')->middleware('auth:api');
    //     Route::post('change-delivery-status', 'Api\V2\DeliveryBoyController@change_delivery_status')->middleware('auth:api');
    // });

    // Route::get('get-menu', 'PageSettingController@show')->middleware('api');
    Route::get('get-app-data', [PageSettingController::class,'appData'])->middleware('api');//done



    // Route::get('chat/conversations/{id}', 'Api\V2\ChatController@conversations')->middleware('auth:api');
    // Route::get('chat/messages/{id}', 'Api\V2\ChatController@messages')->middleware('auth:api');
    // Route::post('chat/insert-message', 'Api\V2\ChatController@insert_message')->middleware('auth:api');
    // Route::get('chat/get-new-messages/{conversation_id}/{last_message_id}', 'Api\V2\ChatController@get_new_messages')->middleware('auth:api');
    // Route::post('chat/create-conversation', 'Api\V2\ChatController@create_conversation')->middleware('auth:api');

    Route::get('banners', [BannerController::class,'index']);//done

    // Brand Route

    Route::get('brands', [BrandController::class,'index']);//done
    Route::get('brands/sitemap', [BrandController::class,'siteMap']);//done
    Route::get('brands/top', [BrandController::class,'top']);//done
    Route::get('brands/all', [BrandController::class,'getAllBrands']);//done
    Route::get('brands/best_brands', [BrandController::class,'bestBrands']);//done
   
    // Business Route

    Route::get('business-settings', [BusinessSettingController::class,'index']);//done

    // Categories Route

    Route::get('categories/top', [CategoryController::class,'top']);//done
    Route::get('categories/sitemap', [CategoryController::class,'sitemap']);//done
    Route::get('categories/featured', [CategoryController::class,'featured']);//done
    Route::get('categories/home', [CategoryController::class,'home']);//done
    Route::get('categories/trending', [CategoryController::class,'trendingCategory']);//done
    Route::get('trendings/{slug}', [CategoryController::class,'trendingSlug']);//done
    Route::get('home/app', [CategoryController::class,'homeApp']);//done
    Route::get('home/app/bd', [CategoryController::class,'homeAppBd']);//done***
    Route::Resource('products', ProductController::class)->except(['store', 'update', 'destroy']);
    Route::get('categories/products/{slug}', [CategoryController::class,'categoryProducts'])->name('v2.categories.products');//done
    Route::get('products/search/{slug}/products', [CategoryController::class,'searchProductsResult'])->name('v2.products.search.products');//done
    Route::get('categories/trendings', [CategoryController::class,'trendingCategories']);//done***
    Route::get('categories/{slug}', [CategoryController::class,'show'])->name('v2.categories.show');
    Route::get('products/search/{slug}', [CategoryController::class,'searchProducts'])->name('v2.products.search');
    Route::get('top-search/products', [CategoryController::class,'topProducts']);
    Route::get('top-search', [CategoryController::class,'topShow']);

    Route::get('brands/{slug}', [BrandController::class,'show'])->name('v2.brands.show');
    Route::get('brands/products/{slug}',[CategoryController::class,'brandProducts'])->name('v2.brands.products');
//     Route::get('brands/offer/{slug}', 'Api\V2\BrandController@showOffer');
//     Route::get('top-search-data', 'Api\V2\ConcernController@topSearchData');
//     Route::get('popular-search', 'Api\V2\ConcernController@popularSearchData');

    Route::get('concerns-data/{slug}', [BrandController::class,'concernShow'])->name('v2.concerns.show');//done***
    Route::get('concerns-data/products/{slug}', [CategoryController::class,'concernProducts'])->name('v2.concerns.products');//done***


//     Route::get('concerns/all', 'Api\V2\ConcernController@getAll');
//     Route::apiResource('concerns', 'Api\V2\ConcernController');
//     Route::apiResource('careers', 'Api\V2\CareerController');
//     Route::get('/careers/{id}','Api\V2\CareerController@show');

//     Route::apiResource('subscribers', 'Api\V2\SubscriberController');

//     Route::get('/pages', 'PageController@pageList');
//     Route::get('/page/{slug}', 'PageController@showPage');

//     Route::apiResource('categories', 'Api\V2\CategoryController')->only('index');
    // Route::get('sub-categories/{id}', 'Api\V2\SubCategoryController@index')->name('subCategories.index');

//     Route::apiResource('colors', 'Api\V2\ColorController')->only('index');

//     Route::apiResource('currencies', 'Api\V2\CurrencyController')->only('index');

//     Route::apiResource('customers', 'Api\V2\CustomerController')->only('show');

//     Route::apiResource('general-settings', 'Api\V2\GeneralSettingController')->only('index');

//     Route::apiResource('home-categories', 'Api\V2\HomeCategoryController')->only('index');

//     //Route::get('purchase-history/{id}', 'Api\V2\PurchaseHistoryController@index')->middleware('auth:api');
//     //Route::get('purchase-history-details/{id}', 'Api\V2\PurchaseHistoryDetailController@index')->name('purchaseHistory.details')->middleware('auth:api');

//     Route::get('purchase-history/{id}', 'Api\V2\PurchaseHistoryController@index');
//     Route::get('purchase-history-details/{id}', 'Api\V2\PurchaseHistoryController@details');
//     Route::get('purchase-history-items/{id}', 'Api\V2\PurchaseHistoryController@items');

//     Route::get('filter/categories', 'Api\V2\FilterController@categories');
//     Route::get('filter/brands', 'Api\V2\FilterController@brands');

//     Route::get('products/admin', 'Api\V2\ProductController@admin');
//     Route::get('products/seller/{id}', 'Api\V2\ProductController@seller');

    Route::get("/best/data", [OfferController::class,'bestData']);//done***
    Route::get('best/product', [CategoryController::class,'bestProduct']);//done***

//     Route::get("/under/data", "OfferController@underData");
//     Route::get('under/product', 'Api\V2\CategoryController@underProduct');


//     Route::get("/campaign/home", "OfferController@campaignHome");

//     Route::get("/campaign/{slug}/data", "OfferController@campaignData");
//     Route::get('campaign/{slug}/product', 'Api\V2\CategoryController@campaignProducts');

//     Route::get("/sale/{slug}/data", "OfferController@saleData");
//     Route::get('sale/{slug}/product', 'Api\V2\CategoryController@saleProducts');


    Route::get("/new/data", [OfferController::class,'newData']);//done***
//     Route::get("/delivery/charge", "OfferController@deliveryCharge");
    Route::get('products/category/{id}', [ProductController::class,'category'])->name('api.products.category');
//     Route::get('products/sub-category/{id}', 'Api\V2\ProductController@subCategory')->name('products.subCategory');
//     Route::get('products/sub-sub-category/{id}', 'Api\V2\ProductController@subSubCategory')->name('products.subSubCategory');
    Route::get('products/brand/{id}', [ProductController::class,'brand'])->name('api.products.brand');
//     Route::get('products/todays-deal', 'Api\V2\ProductController@todaysDeal');
//     Route::get('products/featured', 'Api\V2\ProductController@featured');
//     Route::get('products/best-seller', 'Api\V2\ProductController@bestSeller');
    Route::get('campaign/products', [ProductController::class,'campaignProduct']);
//     Route::get('campaign/products/{id}', 'Api\V2\ProductController@campaignProductId');
//     Route::get('campaign/product', 'Api\V2\CategoryController@campaignProduct');
//     Route::get('val/products', 'Api\V2\ProductController@valProduct');
//     Route::get('fifty/products', 'Api\V2\ProductController@fiftyProduct');
   
    Route::get('new/product', [CategoryController::class,'newProduct']);//done***
//     Route::get('products/stock/{slug}', 'Api\V2\ProductController@stock')->name('products.stock');
//     Route::get('products/related/{id}', 'Api\V2\ProductController@related')->name('products.related');

//     Route::get('products/featured-from-seller/{id}', 'Api\V2\ProductController@newFromSeller')->name('products.featuredromSeller');
//     Route::get('products/search', 'Api\V2\ProductController@search');
//     Route::get('products/variant/price', 'Api\V2\ProductController@variantPrice');
//     Route::get('products/home', 'Api\V2\ProductController@home');
    Route::get('products/section/{section_name}', [ProductController::class,'sectionProducts']);
   


//     Route::get('products/{slug}/recommend', 'Api\V2\ProductController@recommend');
//     Route::get('product/sitemap', 'Api\V2\ProductController@siteMap');
//     Route::get('collects/sitemap', 'Api\V2\ProductController@collectSiteMap');
//     Route::get('image/sitemap', 'Api\V2\ProductController@imageSiteMap');


//     Route::get('cart-summary/{owner_id}/{user_id?}', 'Api\V2\CartController@summary');

//     Route::post('carts/process', 'Api\V2\CartController@process')->middleware('auth:api');
//     Route::post('carts/add', 'Api\V2\CartController@add')->middleware('auth:api');

//     Route::post('carts/change-quantity', 'Api\V2\CartController@changeQuantity');
//     Route::post('carts/increment', 'Api\V2\CartController@increment');
//     Route::post('carts/decrement', 'Api\V2\CartController@decrement');

//     Route::post('carts/add-free', 'Api\V2\CartController@addFree');
//     Route::post('carts/remove-free', 'Api\V2\CartController@removeFree');
//     Route::apiResource('carts', 'Api\V2\CartController')->only('destroy');
//     Route::post('carts/{user_id}', 'Api\V2\CartController@getList')->middleware('auth:api');
//     //get cart list for temp user
    Route::get('carts/item/{user_id}', [CartController::class,'getCartItem']);
//     Route::get('carts/validate/{user_id}', 'Api\V2\CartController@validateCartItem');
//     Route::post('carts/add/{user_id}', 'Api\V2\CartController@add');


//     Route::get('carts/remove-all/{user_id}', 'Api\V2\CartController@removeAll');






//     Route::post('coupon-apply', 'Api\V2\CheckoutController@apply_coupon_code')->middleware('auth:api');
//     Route::post('coupon-list', 'Api\V2\CheckoutController@coupon_list')->middleware('auth:api');
//     Route::post('coupon-remove', 'Api\V2\CheckoutController@remove_coupon_code');

//     Route::post('update-address-in-cart', 'Api\V2\AddressController@updateAddressInCart')->middleware('auth:api');

//     Route::get('payment-types', 'Api\V2\PaymentTypesController@getList');

//     Route::get('reviews/product/{slug}', 'Api\V2\ReviewController@index')->name('api.reviews.index');
//     Route::get('reviews/permission/{slug}', 'Api\V2\ReviewController@permission')->name('api.reviews.permission')->middleware('auth:api');
//     Route::post('reviews/submit', 'Api\V2\ReviewController@submit')->name('api.reviews.submit')->middleware('auth:api');
//     Route::post('reviews/update', 'Api\V2\ReviewController@update')->name('api.reviews.update')->middleware('auth:api');


//     Route::get('shop/user/{id}', 'Api\V2\ShopController@shopOfUser')->middleware('auth:api');
//     Route::get('shops/details/{id}', 'Api\V2\ShopController@info')->name('shops.info');
//     Route::get('shops/products/all/{id}', 'Api\V2\ShopController@allProducts')->name('shops.allProducts');
//     Route::get('shops/products/top/{id}', 'Api\V2\ShopController@topSellingProducts')->name('shops.topSellingProducts');
//     Route::get('shops/products/featured/{id}', 'Api\V2\ShopController@featuredProducts')->name('shops.featuredProducts');
//     Route::get('shops/products/new/{id}', 'Api\V2\ShopController@newProducts')->name('shops.newProducts');
//     Route::get('shops/brands/{id}', 'Api\V2\ShopController@brands')->name('shops.brands');
//     Route::apiResource('shops', 'Api\V2\ShopController')->only('index');

//     Route::apiResource('sliders', 'Api\V2\SliderController')->only('index');
    Route::get('mobile-sliders', [SliderController::class,'mobileSliders']);//done***
//     Route::get('wishlists-check-product', 'Api\V2\WishlistController@isProductInWishlist')->middleware('auth:api');;
//     Route::get('wishlists-add-product', 'Api\V2\WishlistController@add')->middleware('auth:api');;
//     Route::get('wishlists-remove-product', 'Api\V2\WishlistController@remove')->middleware('auth:api');;
    Route::get('wishlists', [WishlistController::class,'index'])->middleware('auth:api');
//     Route::apiResource('wishlists', 'Api\V2\WishlistController')->except(['index', 'update', 'show'])->middleware('auth:api');

//     Route::apiResource('settings', 'Api\V2\SettingsController')->only('index');

//     Route::get('policies/seller', 'Api\V2\PolicyController@sellerPolicy')->name('policies.seller');
//     Route::get('policies/support', 'Api\V2\PolicyController@supportPolicy')->name('policies.support');
//     Route::get('policies/return', 'Api\V2\PolicyController@returnPolicy')->name('policies.return');

//     Route::get('user/info/{id}', 'Api\V2\UserController@info')->middleware('auth:api');
//     Route::post('user/info/update', 'Api\V2\UserController@updateName')->middleware('auth:api');
//     Route::get('user/shipping/address', 'Api\V2\AddressController@addresses')->middleware('auth:api');
//     Route::get('user/shipping/address/{id}', 'Api\V2\AddressController@viewAddress')->middleware('auth:api');
//     Route::post('user/shipping/create', 'Api\V2\AddressController@createShippingAddress')->middleware('auth:api');
//     Route::post('user/shipping/update', 'Api\V2\AddressController@updateShippingAddress')->middleware('auth:api');
//     Route::post('user/shipping/update-location', 'Api\V2\AddressController@updateShippingAddressLocation')->middleware('auth:api');
//     Route::post('user/shipping/make_default', 'Api\V2\AddressController@makeShippingAddressDefault')->middleware('auth:api');
//     Route::get('user/shipping/delete/{id}', 'Api\V2\AddressController@deleteShippingAddress')->middleware('auth:api');

//     Route::get('clubpoint/get-list/{id}', 'Api\V2\ClubpointController@get_list')->middleware('auth:api');
//     Route::post('clubpoint/convert-into-wallet', 'Api\V2\ClubpointController@convert_into_wallet')->middleware('auth:api');

//     Route::get('refund-request/get-list/{id}', 'Api\V2\RefundRequestController@get_list')->middleware('auth:api');
//     Route::post('refund-request/send', 'Api\V2\RefundRequestController@send')->middleware('auth:api');

//     Route::post('get-user-by-access_token', 'Api\V2\UserController@getUserInfoByAccessToken');

//     Route::get('cities', 'Api\V2\AddressController@getCities');
//     Route::get('countries', 'Api\V2\AddressController@getCountries');

//     Route::post('shipping_cost', 'Api\V2\ShippingController@shipping_cost')->middleware('auth:api');
//     Route::get('shipping_info', 'Api\V2\ShippingController@shipping_info');
//     Route::post('coupon/apply', 'Api\V2\CouponController@apply')->middleware('auth:api');


//     Route::any('stripe', 'Api\V2\StripeController@stripe');
//     Route::any('/stripe/create-checkout-session', 'Api\V2\StripeController@create_checkout_session')->name('api.stripe.get_token');
//     Route::any('/stripe/payment/callback', 'Api\V2\StripeController@callback')->name('api.stripe.callback');
//     Route::any('/stripe/success', 'Api\V2\StripeController@success')->name('api.stripe.success');
//     Route::any('/stripe/cancel', 'Api\V2\StripeController@cancel')->name('api.stripe.cancel');

//     Route::any('paypal/payment/url', 'Api\V2\PaypalController@getUrl')->name('api.paypal.url');
//     Route::any('paypal/payment/done', 'Api\V2\PaypalController@getDone')->name('api.paypal.done');
//     Route::any('paypal/payment/cancel', 'Api\V2\PaypalController@getCancel')->name('api.paypal.cancel');

//     Route::any('razorpay/pay-with-razorpay', 'Api\V2\RazorpayController@payWithRazorpay')->name('api.razorpay.payment');
//     Route::any('razorpay/payment', 'Api\V2\RazorpayController@payment')->name('api.razorpay.payment');
//     Route::post('razorpay/success', 'Api\V2\RazorpayController@success')->name('api.razorpay.success');

//     Route::any('paystack/init', 'Api\V2\PaystackController@init')->name('api.paystack.init');
//     Route::post('paystack/success', 'Api\V2\PaystackController@success')->name('api.paystack.success');

//     Route::any('iyzico/init', 'Api\V2\IyzicoController@init')->name('api.iyzico.init');
//     Route::any('iyzico/callback', 'Api\V2\IyzicoController@callback')->name('api.iyzico.callback');
//     Route::post('iyzico/success', 'Api\V2\IyzicoController@success')->name('api.iyzico.success');

//     Route::get('bkash/begin', 'Api\V2\BkashController@begin')->middleware('auth:api');
//     Route::get('bkash/api/webpage/{token}/{amount}', 'Api\V2\BkashController@webpage')->name('api.bkash.webpage');
//     Route::any('bkash/api/checkout/{token}/{amount}', 'Api\V2\BkashController@checkout')->name('api.bkash.checkout');
//     Route::any('bkash/api/execute/{token}', 'Api\V2\BkashController@execute')->name('api.bkash.execute');
//     Route::any('bkash/api/fail', 'Api\V2\BkashController@fail')->name('api.bkash.fail');
//     Route::any('bkash/api/success', 'Api\V2\BkashController@success')->name('api.bkash.success');
//     Route::post('bkash/api/process', 'Api\V2\BkashController@process')->name('api.bkash.process');

//     Route::get('nagad/begin', 'Api\V2\NagadController@begin')->middleware('auth:api');
//     Route::any('nagad/verify/{payment_type}', 'Api\V2\NagadController@verify')->name('app.nagad.callback_url');
//     Route::post('nagad/process', 'Api\V2\NagadController@process');

//     Route::get('sslcommerz/begin', 'Api\V2\SslCommerzController@begin');
//     Route::post('sslcommerz/success', 'Api\V2\SslCommerzController@payment_success');
//     Route::post('sslcommerz/fail', 'Api\V2\SslCommerzController@payment_fail');
//     Route::post('sslcommerz/cancel', 'Api\V2\SslCommerzController@payment_cancel');

//     Route::post('payments/pay/wallet', 'Api\V2\WalletController@processPayment')->middleware('auth:api');
//     Route::post('payments/pay/cod', 'Api\V2\PaymentController@cashOnDelivery')->middleware('auth:api');

//     Route::post('order/store', 'Api\V2\OrderController@store')->middleware('auth:api');
//     Route::get('order/list', 'Api\V2\OrderController@orderList')->middleware('auth:api');
//     Route::get('order/show/{id}', 'Api\V2\OrderController@orderDetails');
//     Route::get('order/pay', 'Api\V2\OrderController@pay');
//     Route::post('/bkash/create/{order}', 'Api\V2\BkashPayController@create')->name('url-create');
// Route::get('/bkash/callback', 'Api\V2\BkashPayController@callback')->name('url-callback');

// // Checkout (URL) Admin Part
// // Route::post('/bkash/refund', [CheckoutURLController::class, 'refund'])->name('url-post-refund');
//     Route::get('profile/counters/{user_id}', 'Api\V2\ProfileController@counters')->middleware('auth:api');
//     Route::get('profile/info', 'Api\V2\ProfileController@getInfo')->middleware('auth:api');
//     Route::post('profile/info', 'Api\V2\ProfileController@storeInfo')->middleware('auth:api');
//     Route::post('profile/update', 'Api\V2\ProfileController@update')->middleware('auth:api');
//     Route::post('profile/update-device-token', 'Api\V2\ProfileController@update_device_token')->middleware('auth:api');
//     Route::post('profile/update-image', 'Api\V2\ProfileController@updateImage')->middleware('auth:api');
//     Route::post('profile/image-upload', 'Api\V2\ProfileController@imageUpload')->middleware('auth:api');

//     Route::get('wallet/balance/{id}', 'Api\V2\WalletController@balance')->middleware('auth:api');
//     Route::get('wallet/history/{id}', 'Api\V2\WalletController@walletRechargeHistory')->middleware('auth:api');

//     //waitlist store

//     Route::post('waitlist', 'Api\V2\WaitlistController@store');
//     Route::get('send-email', 'Api\V2\WaitlistController@sendEmail');

//     Route::post('waitlist/user', 'Api\V2\WaitlistController@storeAuth')->middleware('auth:api');
//     //contact store
//     Route::post('contact', 'Api\V2\ContactController@store');


//     Route::get('flash-deals', 'Api\V2\FlashDealController@index');
//     Route::get('flash-deal-products/{id}', 'Api\V2\FlashDealController@products');

//     //offer route
//     Route::get("/offers", "Api\V2\SearchController@show_offer_products");
//     Route::get("/offers/best-selling", "Api\V2\SearchController@bestSellingOfferProducts");
//     Route::get("/offers/bundle-deal", "Api\V2\SearchController@bundleOfferProducts");
//     Route::get("/offers/50-off", "Api\V2\SearchController@fiftyOff");
//     Route::get("/koi-offer", "Api\V2\SearchController@koiOffer");
//     Route::get("/womens-offer", "Api\V2\SearchController@womensOffer");
//     Route::get("/campaign-offer", "Api\V2\SearchController@womensOffer");


//     // Route::get("/offers/50-off", "Api\V2\SearchController@bestSell");

    Route::get("/offers/sliders", [OfferController::class,'offerSliderList']);//done***
//     Route::get("/free-gift/sliders", "OfferController@freeGiftSliderList");

//     Route::get("/offers/features", "OfferController@offerFeatureList");
//     Route::get("/offers/categories", "OfferController@offerCategoryList");
    Route::get("/offers/concerns", [OfferController::class,'offerConcernList']);//done***
//     Route::get("/offers/concerns/{name}", "Api\V2\ConcernController@showOffer");


    Route::get("/offers/brands", [OfferController::class,'brandList']);
    Route::get("/free-gifts", [OfferController::class,"freeGiftList"]);//done***
//     Route::get('get_banner_data', 'Api\\HomeCategoryController@getBannerData');
});