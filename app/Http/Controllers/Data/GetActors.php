<?php

namespace App\Http\Controllers\Data;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Str;

class GetActors extends FormRequest
{
    use HasConfiguration;

    public function getActors(Request $request)
    {
        $user = auth()->user();
        $actorId = $user->default_actor_id ?? null;

        // 1. Dispatch background request to Foundry to refresh the list
        $requestId = (string) Str::uuid();
        CyberpunkAgentChannel::dispatch([
            'type' => 'GET_ACTORS',
            'requestId' => $requestId,
        ]);

        // 2. Return current known state immediately (if any)
        // Note: For simplicity in the initial settings screen, we could keep polling
        // OR we return a list of previously cached actors.
        // For now, let's keep GetActors synchronous but with the fast 20ms polling
        // unless it's a major bottleneck. The instructions said "GetActor" specifically.
        // However, for consistency let's look at the polling loop.

        // Polling loop (already optimized to 20ms in Phase 1)
        $startTime = microtime(true);
        $timeout = 2.0; // Reduced timeout
        $foundryData = null;

        while ((microtime(true) - $startTime) < $timeout) {
            $key = 'foundry_response_' . $requestId;
            if (Cache::has($key)) {
                $foundryData = Cache::pull($key);
                break;
            }
            usleep(20000); // 20ms
        }

        return response()->json([
            'actors' => $foundryData['actors'] ?? [],
            'uuid' => $actorId,
            'isOffline' => ($foundryData === null)
        ], 200);
    }
}
