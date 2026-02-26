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
        $actorId = $user->getActorId();

        // Dispatch background request to Foundry to refresh the list
        $requestId = $this->createRequestId();

        // We always use GET_ACTORS to get the full available list for the user
        $type = 'GET_ACTORS';
        $payload = [
            'type' => $type,
            'requestId' => $requestId,
            'actorId' => $actorId,
            'foundryUserId' => $user->client_id,
        ];

        CyberpunkAgentChannel::dispatch($payload);

        // Polling loop for live data
        $startTime = microtime(true);
        $timeout = 5.0; // Increased timeout
        $foundryData = null;

        while ((microtime(true) - $startTime) < $timeout) {
            $key = 'foundry_response_' . $requestId;
            if (Cache::has($key)) {
                $foundryData = Cache::pull($key);
                break;
            }
            usleep(20000); // 20ms
        }

        $actors = $foundryData['actors'] ?? [];

        return response()->json([
            'actors' => $actors,
            'uuid' => $actorId,
            'requestId' => $requestId,
            'isOffline' => ($foundryData === null)
        ], 200);
    }
}
