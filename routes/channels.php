<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('cpr-agent.{channelId}', function ($user, $channelId) {
    return $user !== null && $user->channel_id === $channelId;
});
