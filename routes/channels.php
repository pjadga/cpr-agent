<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('cpr-agent', function ($user) {
    // Return true if the user is allowed to listen.
    // In the future, check $user->id or specific permissions here.
    return !is_null($user);
});
