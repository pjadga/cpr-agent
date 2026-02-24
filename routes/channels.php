<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('cpr-agent', function ($user) {
    return $user !== null;
});
