<?php

use App\Http\Controllers\api\v3\AuthController;
use App\Http\Controllers\api\v3\PasswordResetController;
use App\Http\Controllers\api\v3\ProfileController;
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