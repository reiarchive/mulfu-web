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

// callback api
Route::post('/callback/qris', [App\Http\Controllers\CallbackController::class, 'qris']);

// otp api
Route::post('/otp/send', [App\Http\Controllers\OTPController::class, 'sendOTP']);
Route::post('/otp/verify', [App\Http\Controllers\OTPController::class, 'verifyOTP']);

// turnitin api
Route::post('/turnitin/make-payment', [App\Http\Controllers\UploadsController::class, 'setDetailAndMakePayment']);
Route::post('/turnitin/upload', [App\Http\Controllers\UploadsController::class, 'uploadTurnitinFiles']);
Route::post('/turnitin/change-process', [App\Http\Controllers\TurnitinController::class, 'changeProcess']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

