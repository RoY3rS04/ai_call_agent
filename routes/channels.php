<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// TODO only marketing and admin users will be able to listen to these channels
Broadcast::channel('calls', function ($user) {
    return Auth::id() === (int) $user->id;
});

Broadcast::channel('calls.{callSid}', function ($user, $callSid) {
    return Auth::id() === (int) $user->id;
});
