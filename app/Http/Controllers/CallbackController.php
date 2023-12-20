<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{
    public function qris(Request $request) {
        // Log the entire request
        Log::info('Request Data:', ['data' => $request->all()]);
    
        // Your existing code goes here
    }
}
