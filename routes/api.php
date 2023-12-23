<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/callback/qris', [App\Http\Controllers\CallbackController::class, 'qris']);
Route::post('/otp/send', [App\Http\Controllers\OTPController::class, 'sendOTP']);
Route::post('/otp/verify', [App\Http\Controllers\OTPController::class, 'verifyOTP']);

Route::post('/turnitin/make-payment', [App\Http\Controllers\UploadsController::class, 'setDetailAndMakePayment']);
Route::post('/turnitin/upload', [App\Http\Controllers\UploadsController::class, 'uploadTurnitinFiles']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

