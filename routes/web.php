<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {

    if (request()->has('ref')) {
        $referralCode = request('ref');
        return response()->view('welcome')->cookie('refferal_code', $referralCode, 7 * 24 * 60);
    }

    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/invoice/{request_id}', [App\Http\Controllers\TurnitinController::class, 'showInvoice']);
// Route::get('/test', [App\Http\Controllers\UploadsController::class, 'setPayment']);