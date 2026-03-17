<?php

use App\Http\Controllers\TwilioCallController;
use Illuminate\Support\Facades\Route;

Route::get('/welcome', function () {

    $connector = new \React\Socket\Connector();

    //return view('welcome');
});

Route::post('/webhooks/twilio', TwilioCallController::class)
    ->middleware('twilio');
