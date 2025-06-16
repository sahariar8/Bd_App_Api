<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
     public function index()
    {
        $wishlists = Wishlist::where('user_id', Auth::user()->id)->paginate(9);
        return response()->json(['wishlists' => $wishlists]);
        
    }
}
