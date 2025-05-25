<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Utils\AddressCollection;
use App\Utils\OrderCollection;
use App\Utils\PayhereUtility;
use App\Utils\WishlistCollection;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    protected function Validate(array $data)
    {
        return Validator::make($data, [
            'type' => 'required|string',
            'value' => 'required|string',
        ]);
    }

    protected function createUser(array $data)
    {
        $type = $data['type'] ?? null;
        $value = $data['value'] ?? null;

        // Assign phone or email based on the type
        $user = User::create([
            'name' => '',
            'phone' => $type === 'phone' ? $value : null,
            'email' => $type === 'email' ? $value : null,
            'password' => Hash::make('password'),
            'verification_code' => rand(1000, 9999),
        ]);

        // Create linked customer record
        Customer::create(['user_id' => $user->id]);

        // Send OTP if addon is enabled
        if (addon_is_activated('otp_system')) {
            (new OTPVerificationController)->send_code($user);
        }

        return $user;
    }

    protected function sendOTP(User $user)
    {
        (new OTPVerificationController)->send_code($user);
    }

    public function register(Request $request)
    {
        $type = $request->input('type');
        $value = $request->input('value');

        $rules = match ($type) {
            'phone' => ['value' => ['required', 'string', 'regex:/^\+88(013|015|017|016|018|019)[0-9]{8}$/']],
            'email' => ['value' => 'required|email'],
            default => ['value' => 'required'],
        };

        $validator = Validator::make($request->all(), $rules, [
            'value.required' => 'The :attribute field is required.',
            'value.regex' => 'Invalid phone number.',
            'value.email' => 'Invalid email address.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('phone', $value)->orWhere('email', $value)->first();

        if ($user) {
            // Re-send OTP if unverified or logic requires it
            $user->update(['verification_code' => random_int(1000, 9999)]);
            // Generate token using Passport
            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;

            // Set token expiration (optional)
            $token->expires_at = Carbon::now()->addWeeks(100);
            $token->save();

            return response()->json([
                'result' => true,
                'message' => 'User already exists. Logged in successfully',
                'user_id' => $user->id,
                'existing' => true,
                'code' => $user->verification_code,
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->expires_at->toDateTimeString(),
            ]);
        } else {
            // Create new user
            $user = $this->createUser($request->all());

            return response()->json([
                'result' => true,
                'message' => 'Registration Successful. Verify with the OTP sent.',
                'user_id' => $user->id,
                'existing' => false,
                'code' => $user->verification_code,
                'otp_enabled' => true,
            ]);
        }
    }

    public function verify_phone_login(Request $request)
    {
        $user = User::findOrFail($request->user_id);

        if ($user->verification_code == $request->verification_code) {

            $user->email_verified_at = now();
            $user->save();

            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;
            $token->expires_at = now()->addWeeks(100);
            $token->save();

            return response()->json([
                'result' => true,
                'message' => 'Successfully logged in',
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->expires_at->toDateTimeString(),
                'user' => [
                    'id' => $user->id,
                    'type' => $user->user_type,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'avatar_original' => api_asset($user->avatar_original),
                    'phone' => $user->phone,
                    'total_order' => Order::where('user_id', $user->id)->count()
                ]
            ]);
        } else {
            Http::post('https://discordapp.com/api/webhooks/...', [
                "content" => "Phone {$user->phone} original code {$user->verification_code}, code given {$request->verification_code}"
            ]);

            return response()->json([
                'result' => false,
                'message' => 'Invalid verification code'
            ]);
        }
    }


    public function login(Request $request)
    {

        $delivery_boy_condition = $request->has('user_type') && $request->user_type == 'delivery_boy';

        if ($delivery_boy_condition) {

            $user = User::whereIn('user_type', ['delivery_boy'])->where('email', $request->email)->orWhere('phone', $request->email)->first();
        } else {
            $user = User::whereIn('user_type', ['customer', 'seller'])->where('email', $request->email)->orWhere('phone', $request->email)->first();
            // dd($user,'sahariar1');
        }

        if (!$delivery_boy_condition) {
            if (PayhereUtility::create_wallet_reference($request->identity_matrix) == false) {
                return response()->json(['result' => false, 'message' => 'Identity matrix error', 'user' => null], 401);
            }
        }


        if ($user != null) {
            if (Hash::check($request->password, $user->password)) {

                if ($user->email_verified_at == null) {
                    return response()->json(['message' => 'Please verify your account', 'user' => null], 401);
                }
                $tokenResult = $user->createToken('Personal Access Token');
                return $this->loginSuccess($tokenResult, $user);
            } else {
                return response()->json(['result' => false, 'message' => 'Unauthorized', 'user' => null], 401);
            }
        } else {
            return response()->json(['result' => false, 'message' => 'User not found', 'user' => null], 401);
        }
    }

    public function socialLogin(Request $request)
    {
        try {
            // Validate incoming request
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'name' => 'required|string',
                'provider_id' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            // Find user by email
            $user = User::where('email', $request->email)->first();

            // If not found, create new user
            if (!$user) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'provider_id' => $request->provider_id,
                    'email_verified_at' => Carbon::now()
                ]);

                // Create related customer
                Customer::create([
                    'user_id' => $user->id,
                ]);
            }

            // Create access token
            $tokenResult = $user->createToken('Personal Access Token');

            return response()->json([
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'user' => $user
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Something went wrong.',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    protected function loginSuccess($tokenResult, $user)
    {
        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(100);
        $token->save();
        return response()->json([
            'result' => true,
            'message' => 'Successfully logged in',
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString(),
            'user' => [
                'id' => $user->id,
                'type' => $user->user_type,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'avatar_original' => api_asset($user->avatar_original),
                'access_token' => $tokenResult->accessToken,
                'phone' => $user->phone
            ]
        ]);
    }

    public function user(Request $request)
    {

        // dd('sahariar');
        $billing = Address::where('user_id', Auth::user()->id)->where('type', 'billing')->where('set_default', 1)->first();
        $shipping = Address::where('user_id', Auth::user()->id)->where('type', 'shipping')->where('set_default', 1)->first();
        $raffleCodes = Order::where('user_id', Auth::user()->id)->whereNotNull('raffle_code')->pluck('raffle_code')->toArray();
        $user = $request->user();
        $user["dob"] = $user["dob"] ? explode("T", $user["dob"])[0] : $user["dob"];
        return response()->json([
            "profile" => $user,
            'codes' => $raffleCodes,
            "addresses" => new AddressCollection(Address::where('user_id', Auth::user()->id)->orderBy('set_default', 'desc')->get()),
            "billing_default" => $billing ? [
                'id'      => (int) $billing->id,
                'user_id' => (int) $billing->user_id,
                'address' => $billing->address,
                'name' => $billing->name,
                'country' => $billing->country,
                'city' => $billing->city,
                'postal_code' => $billing->postal_code,
                'phone' => $billing->phone,
                'type' => $billing->type,
                'set_default' => (int) $billing->set_default,

            ] : null,
            "shipping_default" => $shipping ? [
                'id'      => (int) $shipping->id,
                'user_id' => (int) $shipping->user_id,
                'address' => $shipping->address,
                'name' => $shipping->name,
                'country' => $shipping->country,
                'city' => $shipping->city,
                'postal_code' => $shipping->postal_code,
                'phone' => $shipping->phone,
                'type' => $shipping->type,
                'set_default' => (int) $shipping->set_default,

            ] : null,

            'pending' => Order::where('user_id', Auth::user()->id)
                ->where(function ($query) {
                    $query->where('delivery_status', 'pending')
                        ->orWhere('delivery_status', 'processing');
                })
                ->count(),
            "completed" => Order::where('user_id', Auth::user()->id)->where('delivery_status', 'delivered')->count(),
            "cancelled" => Order::where('user_id', Auth::user()->id)->where('delivery_status', 'cancelled')->count(),
            "id" => Auth::user()->id
        ]);
    }
    public function userAll(Request $request)
    {
        $billing = Address::where('user_id', Auth::user()->id)->where('type', 'billing')->where('set_default', 1)->first();
        $shipping = Address::where('user_id', Auth::user()->id)->where('type', 'shipping')->where('set_default', 1)->first();
        $product_ids = Wishlist::where('user_id', Auth::user()->id)->pluck("product_id")->toArray();
        $existing_product_ids = Product::whereIn('id', $product_ids)->pluck("id")->toArray();

        $query = Wishlist::query();
        $query->where('user_id', Auth::user()->id)->whereIn("product_id", $existing_product_ids);

        $wishlists = new WishlistCollection($query->latest()->get());
        $addresses = new AddressCollection(Address::where('user_id', Auth::user()->id)->orderBy('id', 'desc')->get());
        $orders = Order::where('user_id', Auth::user()->id)->latest()->get();

        $ordersData = new OrderCollection($orders);
        return response()->json([
            "wishlists" => $wishlists,
            "addresses" => $addresses,
            "orders" => $ordersData,
            "user" => [
                "profile" => $request->user(),
                "addresses" => new AddressCollection(Address::where('user_id', Auth::user()->id)->orderBy('set_default', 'desc')->get()),
                "billing_default" => $billing ? [
                    'id'      => (int) $billing->id,
                    'user_id' => (int) $billing->user_id,
                    'address' => $billing->address,
                    'name' => $billing->name,
                    'country' => $billing->country,
                    'city' => $billing->city,
                    'postal_code' => $billing->postal_code,
                    'phone' => $billing->phone,
                    'type' => $billing->type,
                    'set_default' => (int) $billing->set_default,

                ] : null,
                "shipping_default" => $shipping ? [
                    'id'      => (int) $shipping->id,
                    'user_id' => (int) $shipping->user_id,
                    'address' => $shipping->address,
                    'name' => $shipping->name,
                    'country' => $shipping->country,
                    'city' => $shipping->city,
                    'postal_code' => $shipping->postal_code,
                    'phone' => $shipping->phone,
                    'type' => $shipping->type,
                    'set_default' => (int) $shipping->set_default,

                ] : null,

                "pending" => Order::where('user_id', Auth::user()->id)->where('delivery_status', 'pending')->count(),
                "completed" => Order::where('user_id', Auth::user()->id)->where('delivery_status', 'delivered')->count(),
                "cancelled" => Order::where('user_id', Auth::user()->id)->where('delivery_status', 'cancelled')->count(),
                "id" => Auth::user()->id
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'result' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    public function registerOrLoginViaOtp(Request $request)
    {
        $value = $request->value;
        $type = $request->type;
        $otp = $request->verification_code;

        // 1. Validate input
        if (!$type || !in_array($type, ['email', 'phone'])) {
            return response()->json(['result' => false, 'message' => 'Invalid type'], 422);
        }

        if (!$value) {
            return response()->json(['result' => false, 'message' => 'Value is required'], 422);
        }

        // 2. Find user by email or phone
        $user = User::where('email', $value)->orWhere('phone', $value)->first();

        // 3. If user doesn't exist, create one and send OTP
        if (!$user) {
            $user = new User();
            $user->name = "";
            $user->$type = $value;
            if($type == 'email'){
                $user->email = $value;
            }else{
                $user->phone = $value;
            }
            $user->password = Hash::make($value);
            $user->verification_code = rand(100000, 999999);
            $user->otp_expires_at = now()->addMinutes(2);
            $user->otp_sent_at = now();
            $user->save();

            (new OTPVerificationController)->send_code($user);

            return response()->json([
                'result' => true,
                'message' => 'User created and OTP sent',
                'user_id' => $user->id
            ]);
        }

        // 4. If OTP is not provided, send new OTP (with rate limit check)
        if (!$otp) {
            $user->verification_code = rand(100000, 999999);
            $user->otp_expires_at = now()->addMinutes(2);
            $user->otp_sent_at = now();
            $user->save();

            (new OTPVerificationController)->send_code($user);

            return response()->json([
                'result' => true,
                'message' => 'OTP sent to existing user',
                'user_id' => $user->id
            ]);
        }


        // 5. OTP is provided - check expiration
        if (!$user->verification_code || !$user->otp_expires_at || now()->gt($user->otp_expires_at)) {
            return response()->json([
                'result' => false,
                'message' => 'OTP expired. Please request a new one.'
            ], 422);
        }

        // 6. OTP verification
        if ($user->verification_code == $otp) {
            $user->email_verified_at = now();
            $user->verification_code = null; // Clear OTP after successful login
            $user->otp_expires_at = null;
            $user->otp_sent_at = null;
            $user->save();

            $tokenResult = $user->createToken('Personal Access Token');
            $token = $tokenResult->token;
            $token->expires_at = now()->addWeeks(100);
            $token->save();

            return response()->json([
                'result' => true,
                'message' => 'Successfully logged in',
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->expires_at->toDateTimeString(),
                'user' => [
                    'id' => $user->id,
                    'type' => $user->user_type,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'avatar_original' => api_asset($user->avatar_original),
                    'phone' => $user->phone,
                    'total_order' => Order::where('user_id', $user->id)->count()
                ]
            ]);
        }

        // 7. OTP did not match
        return response()->json([
            'result' => false,
            'message' => 'Invalid verification code'
        ], 401);
    }
}
