<?php

namespace App\Http\Handlers;

use App\Events\FoundryResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Broadcasts Foundry responses to the WebSocket channel and caches for polling.
 */
class BroadcastHandler
{
    /**
     * Broadcast a payload to the appropriate channel and cache it for polling.
     */
    public function handle(array $payload, ?string $agentLink = null, ?string $userChannelId = null): void
    {
        // Enrich with agent_link if available
        if ($agentLink) {
            $payload['agentLink'] = $agentLink;
        }

        // Add channel_id from user data or incoming payload (priority: user > incoming)
        $incomingChannelId = $payload['channel_id'] ?? $payload['channelId'] ?? null;
        if ($userChannelId) {
            $payload['channel_id'] = $userChannelId;
        } elseif ($incomingChannelId) {
            $payload['channel_id'] = $incomingChannelId;
        }

        // Dispatch WebSocket event
        FoundryResponse::dispatch($payload);

        // Cache for synchronous polling (TTL: 10 seconds)
        if (! empty($payload['requestId'])) {
            Cache::put('foundry_response_'.$payload['requestId'], $payload, 10);
        }
    }
}
