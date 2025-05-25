<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\SocialProvider;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(SocialProvider $provider, Request $request)
    {
        // Redirect to the social provider's authentication page       
       return Socialite::driver($provider->value)->redirect();
    }
    public function callback(SocialProvider $provider,Request $request)
    {
       $providerUser = Socialite::driver($provider->value)->user();

       dd($providerUser);
    }
    
}
