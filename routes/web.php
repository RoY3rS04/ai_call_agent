<?php

use App\Http\Controllers\TwilioCallController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/welcome', function () {

    return view('welcome');
});

Route::post('/webhooks/twilio', TwilioCallController::class)
    ->middleware('twilio');
