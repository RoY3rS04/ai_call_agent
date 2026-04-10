<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// TODO only marketing and admin users will be able to listen to these channels
Broadcast::channel('calls', function (User $user) {
    return $user->hasAnyRole('marketing', 'sales', 'admin') ||
        $user->hasPermissionTo('manage-calls');
});

Broadcast::channel('calls.{callSid}', function ($user) {
    return $user->hasAnyRole('marketing', 'sales', 'admin') ||
        $user->hasPermissionTo('manage-calls');
});
