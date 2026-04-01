<?php

use App\Http\Controllers\TwilioCallController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Socialite;

Route::get('/welcome', function () {

    return view('welcome');
});

Route::post('/webhooks/twilio', [TwilioCallController::class, 'incoming'])
    ->middleware('twilio');

Route::post('/webhooks/twilio/call-status', [TwilioCallController::class, 'callStatus']);

Route::get('/auth/google/redirect', function () {
    return Socialite::driver('google')
        ->scopes([
            'openid',
            'profile',
            'email',
            'https://www.googleapis.com/auth/calendar',
        ])
        ->with([
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ])
        ->redirect();
})->name('google.redirect');

Route::get('/auth/google/callback', function (): RedirectResponse {
    $user = auth()->user();
    $googleUser = Socialite::driver('google')->user();

    $user->update([
        'google_id' => $googleUser->getId(),
        'google_email' => $googleUser->getEmail(),
        'google_access_token' => $googleUser->token,
        'google_refresh_token' => $googleUser->refreshToken,
        'google_token_expires_at' => now()->addSeconds($googleUser->expiresIn),
        'google_calendar_id' => 'primary',
        'google_calendar_connected_at' => now(),
    ]);

    return redirect('/');
});
