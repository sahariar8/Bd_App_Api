<?php

namespace App\Http\Controllers\api\v3;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Intervention\Image\ImageManager;
use Intervention\Image\Laravel\Facades\Image;



class ProfileController extends Controller
{
    public function counters($user_id)
    {
        return response()->json([
            'cart_item_count' => Cart::where('user_id', $user_id)->count(),
            'wishlist_item_count' => Wishlist::where('user_id', $user_id)->count(),
            'order_count' => Order::where('user_id', $user_id)->count(),
        ]);
    }

    public function getProfile()
    {
        $user = Auth::user();
        $step = 0;
        $keys = [
            "dob",
            "gender",
            "skin_type",
            "skin_tone",
            "skincare_concerns",
            "makeup_focus",
            "hair_concerns",
            "interests",
            "brands",
            "name"
        ];
        for ($i = 0; $i < count($keys); $i++) {
            if (!$user[$keys[$i]]) {
                break;
            }
            $step++;
        }
        return [
            "step" => $step,
            "data" => [
                [
                    "question" => "What Is Your Date Of Birth? (So We Can Send You Something Special)",
                    "options" => [],
                    "answer" => $user->dob,
                    "key" => "dob",
                    "type" => "date"
                ],
                [
                    "question" => "Which Gender Do You Identify As",
                    "options" => [
                        "Female",
                        "Male",
                        "Non-Binary",
                        "Rather Not Say"
                    ],
                    "answer" => $user->gender,
                    "key" => "gender",
                    "type" => "radio"
                ],
                [
                    "question" => "What Is Your Skin Type?",
                    "options" => [
                        "Combination",
                        "Dry",
                        "Mature",
                        "Normal",
                        "Oily"
                    ],
                    "answer" => $user->skin_type,
                    "key" => "skin_type",
                    "type" => "radio"

                ],
                [
                    "question" => "What Is Your Skin Tone?",
                    "options" => [
                        "Dark",
                        "Fair",
                        "Light",
                        "Medium",
                        "Olive",
                        "Tan",
                        "Deep"
                    ],
                    "answer" => $user->skin_tone,
                    "key" => "skin_tone",
                    "type" => "radio"

                ],
                [
                    "question" => "What Are Your Skincare Concerns? (Select As Many As Needed)",
                    "options" => [
                        "Blackheads",
                        "Dark Circles",
                        "Dryness",
                        "Loose Of Leasticity",
                        "Redness",
                        "Sensitivity",
                        "Sun Damage",
                        "Organic"

                    ],
                    "answer" => $user->skincare_concerns ? json_encode($user->skincare_concerns) : [],
                    "key" => "skincare_concerns",
                    "type" => "checkbox"

                ],
                [
                    "question" => "What Is Your Make Up Focus? (Select As Many As Needed)",
                    "options" => [
                        "Brightness",
                        "Dark Circles",
                        "Dryness",
                        "Loose Of Leasticity",
                        "Redness",
                        "Sensitivity",
                        "Sun Damage",
                        "Organic"
                    ],
                    "answer" => $user->makeup_focus ? json_decode($user->makeup_focus) : [],
                    "key" => "makeup_focus",
                    "type" => "checkbox"

                ],
                [
                    "question" => "What Are Your Hair Concerns? (Select As Many As Needed)",
                    "options" => [
                        "Color Protection",
                        "Dandruff",
                        "Frizz",
                        "Greasiness",
                        "Sensitive Scalp",
                        "Volume",
                        "Dry",
                        "Fine"
                    ],
                    "answer" => $user->hair_concerns ? json_decode($user->hair_concerns) : [],
                    "key" => "hair_concerns",
                    "type" => "checkbox"

                ],
                [
                    "question" => "What Are Your Categories Of Interest? (Select As Many As Needed)",
                    "options" => [
                        "Fragrance",
                        "Hair Care",
                        "Make Up",
                        "New Arrival",
                        "Skin Care",
                        "Well Being"
                    ],
                    "answer" => $user->interests ? json_decode($user->interests) : [],
                    "key" => "interests",
                    "type" => "checkbox"

                ],
                [
                    "question" => "Which Brand(S) Do You Wish We Stocked?",
                    "options" => [
                        "Fragrance"
                    ],
                    "answer" => $user->brands ? json_decode($user->brands) : [],
                    "key" => "brands",
                    "type" => "checkbox"

                ],

            ]
        ];
    }
    public function getInfo()
    {

        return response()->json(
            $this->getProfile()
        );
    }


    public function storeInfo(Request $request)
    {
        $keys = [
            "dob",
            "gender",
            "skin_type",
            "skin_tone",
            "skincare_concerns",
            "makeup_focus",
            "hair_concerns",
            "interests",
            "brands",
            "name"
        ];

        $request->validate([
            'key' => ['required', Rule::in($keys)],
            'answer' => ['required'],
        ]);

        $user = Auth::user();

        $value = is_array($request->answer) ? json_encode($request->answer) : $request->answer;

        $user->{$request->key} = $value; // ✅ safer and more explicit
        $user->save();

        return response()->json(
            $this->getProfile()
        );
    }

    // public function storeInfo(Request $request)
    // {
    //     $keys = [
    //         "dob",
    //         "gender",
    //         "skin_type",
    //         "skin_tone",
    //         "skincare_concerns",
    //         "makeup_focus",
    //         "hair_concerns",
    //         "interests",
    //         "brands",
    //         "name"
    //     ];
    //     $user = Auth::user();
    //     // $request->validate([
    //     //     'key' => ['required', Rule::in($keys)],
    //     // ]);
    //     $user[$request->key] = is_array($request->answer) ? json_encode($request->answer) : $request->answer;
    //     $user->save();
    //     return response()->json(
    //         $this->getProfile()
    //     );
    // }

    public function update(Request $request)
    {
        $user = Auth::user();
        // dd($user);

        $user->name = $request->name;
        if ($request->has('dob')) {
            $user->dob = $request->dob;
        }

        $user->save();

        return response()->json([
            'result' => true,
            'message' => "Profile information updated",
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'old_password' => 'required',
            'password' => 'required|confirmed|min:6',
        ]);

        if (Hash::check($request->old_password, $user->password)) {
            $user->password = Hash::make($request->password);
            $user->save();
            return response()->json([
                'result' => true,
                'message' => "Password updated",
            ]);
        } else {
            return response()->json([
                'result' => false,
                'message' => "Old password does not match",
            ]);
        }
    }

    public function update_device_token(Request $request)
    {
        $user = User::find($request->id);
        $user->device_token = $request->device_token;


        $user->save();

        return response()->json([
            'result' => true,
            'message' => "device token updated",
            "user" => $user
        ]);
    }

    public function imageUpload(Request $request)
    {
        $user = Auth::user();
        // dd($request->all());

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp',
        ]);

        try {
            if ($request->hasFile('image')) {
                // 🧼 Delete old image if it exists
                if (!empty($user->image) && file_exists(public_path($user->image))) {
                    unlink(public_path($user->avatar));
                }

                // 🖼️ Process new image
                $image = $request->file('image');
                $imageName = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
                $uploadPath = 'upload/customer/' . $imageName;
                $savePath = public_path($uploadPath);

                // Ensure directory exists
                if (!file_exists(dirname($savePath))) {
                    mkdir(dirname($savePath), 0755, true);
                }

                // Resize and save image
                $manager = new ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $img = $manager->read($image);
                $img->resize(300, 200);
                $img->save($savePath);

                // Save to user model
                $user->avatar = $uploadPath;
                $user->save();

                // dd($user);

                return response()->json([
                    'result' => true,
                    'message' => "Profile image updated",
                    'user' => $user,
                ]);
            }

            // 🔴 No image uploaded
            return response()->json([
                'result' => false,
                'message' => 'No image file found in request.',
            ], 400);
        } catch (\Exception $e) {
            // 🔴 Unexpected error
            return response()->json([
                'result' => false,
                'message' => 'Image upload failed. ' . $e->getMessage(),
            ], 500);
        }
    }
}
