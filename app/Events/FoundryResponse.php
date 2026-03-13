<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FoundryResponse implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payload;

    public function __construct($payload)
    {
        $this->payload = $payload;
    }

    //Needs to be the same channel as the Agent
    public function broadcastOn(): array
    {
        $channelId = $this->payload['channel_id'] ?? null;

        if ($channelId) {
            return [
                new PrivateChannel('cpr-agent.' . $channelId),
            ];
        }

        return [];
    }

    public function broadcastAs(): string
    {
        return 'FoundryResponse';
    }
}
