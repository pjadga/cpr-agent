<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // <--- IMPORTANT
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CyberpunkAgentChannel implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $payload;

    /**
     * Create a new event instance.
     */
    public function __construct($data)
    {
        // Automatically inject agent_link if not present and user is logged in
        if (!isset($data['agent_link']) && !isset($data['agentLink']) && auth()->check()) {
            $user = auth()->user();
            if ($user->agent_link) {
                $data['agent_link'] = $user->agent_link;
            }
        }

        // This public property $payload is what gets sent as JSON
        $this->payload = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Must match the channel name in your Foundry module
        // We used: this.echo.private('cyberpunk-relay')
        return [
            new PrivateChannel('cpr-agent'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        // Must match the .listen('.GameUpdate') in your JS
        return 'GameUpdate';
    }
}
