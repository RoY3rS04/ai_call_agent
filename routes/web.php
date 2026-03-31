<?php

use App\Http\Controllers\TwilioCallController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

Route::get('/welcome', function () {

    return view('welcome');
});

Route::post('/webhooks/twilio', [TwilioCallController::class, 'incoming'])
    ->middleware('twilio');

Route::post('/webhooks/twilio/call-status', [TwilioCallController::class, 'callStatus']);
