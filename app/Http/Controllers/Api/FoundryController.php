<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Handlers\BroadcastHandler;
use App\Http\Handlers\DeltaSyncHandler;
use App\Http\Handlers\IdentifyUserHandler;
use App\Http\Handlers\LinkPlayerHandler;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FoundryController extends Controller
{
    /**
     * Handle the callback from Foundry.
     *
     * Orchestrates: validation → user identification → handler dispatch → broadcast.
     */
    public function handle(Request $request)
    {
        $start = microtime(true);

        // 1. Validate incoming payload
        $validated = $this->validatePayload($request);
        $type = $validated['type'] ?? null;

        // 2. Identify the user
        $identifyHandler = new IdentifyUserHandler;
        $user = $identifyHandler->handle($request, $validated);

        // 3. Dispatch to appropriate handler
        $result = $this->dispatchHandler($user, $type, $validated, $request);

        // 4. Broadcast response (unless handler returned early)
        if ($result !== null && is_array($result)) {
            return response()->json($result);
        }

        // 5. Broadcast to WebSocket + cache for polling
        $agentLink = $validated['agentLink'] ?? $validated['agent_link'] ?? null;
        $userChannelId = $user?->channel_id;
        (new BroadcastHandler)->handle($validated, $agentLink, $userChannelId);

        $duration = round((microtime(true) - $start) * 1000, 2);
        Log::info("Foundry Callback [{$type}] processed in {$duration}ms", [
            'requestId' => $validated['requestId'] ?? 'N/A',
        ]);

        return response()->json(['status' => 'acknowledged', 'processing_ms' => $duration]);
    }

    /**
     * Validate the incoming Foundry callback payload.
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'requestId' => 'nullable|string',
            'type' => 'required|string',
            'action' => 'nullable|string',
            'actorId' => 'nullable|string',
            'success' => 'nullable|boolean',
            'result' => 'nullable',
            'actors' => 'nullable|array',
            'actor' => 'nullable',
            'tokens' => 'nullable|array',
            'data' => 'nullable|array',
            'total' => 'nullable',
            'isCriticalSuccess' => 'nullable|boolean',
            'isCriticalFailure' => 'nullable|boolean',
            'label' => 'nullable|string',
            'rawRoll' => 'nullable|array',
            'delta' => 'nullable|array',
            'tab' => 'nullable|string',
            'character' => 'nullable',
            'foundryUserId' => 'nullable|string',
            'agentLink' => 'nullable|string',
            'isGM' => 'nullable|boolean',
            'playerName' => 'nullable|string',
            'flavor' => 'nullable|string',
            'formula' => 'nullable|string',
            'target' => 'nullable|string',
            'tokenId' => 'nullable|string',
            'channelId' => 'nullable|string',
            'channel_id' => 'nullable|string',
            'message' => 'nullable|string',
            'rollMode' => 'nullable|string',
            'pools' => 'nullable|array',
            'nonce' => 'nullable|string',
        ]);
    }

    /**
     * Dispatch to the appropriate handler based on message type.
     * Returns early if a handler produces a direct response (e.g., link requests).
     */
    private function dispatchHandler(?User $user, ?string $type, array $payload, Request $request): ?array
    {
        // Link player character events produce direct responses
        if (in_array($type, ['LINK_PLAYER_CHARACTER', 'GM_LINK_PLAYER_CHARACTER'])) {
            return app(LinkPlayerHandler::class)->handle($user ?? $request->user(), $payload);
        }

        // Update is_gm status if present
        if ($user && isset($payload['isGM'])) {
            if ($user->is_gm !== (bool) $payload['isGM']) {
                $user->is_gm = $payload['isGM'];
                $user->save();
                Log::info("User [{$user->id}] is_gm updated to [{$user->is_gm}] via {$type}");
            }
        }

        // Persist actor data / sheet deltas
        if (in_array($type, ['ACTOR_DATA', 'SHEET_DELTA']) && $user) {
            app(DeltaSyncHandler::class)->handle($user->id, $type, $payload);
        }

        return null; // Continue to broadcast
    }

    /**
     * Handle bulk updates from Foundry.
     */
    public function update(Request $request)
    {
        // Legacy endpoint — delegates to handle() for now
        return $this->handle($request);
    }
}
