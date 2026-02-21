<?php

namespace App\Http\Controllers\Data;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Str;

class GetActor extends FormRequest
{
    use HasConfiguration;

    public function getActor(Request $request)
    {
        // 1. Generate a unique ID for this specific transaction
        $requestId = (string) Str::uuid();

        CyberpunkAgentChannel::dispatch([
            'type' => 'GET_ACTOR',
            'requestId' => $requestId,
            'actorId' => str_replace("Actor.", "", $request->get('actorId'))
        ]);

        // 2. Wait for the response (The Polling Loop)
        $startTime = microtime(true);
        $timeout = 3.0; // Seconds to wait
        $foundryData = null;

        while ((microtime(true) - $startTime) < $timeout) {
            $key = 'foundry_response_' . $requestId;

            if (Cache::has($key)) {
                $foundryData = Cache::pull($key);
                break;
            }

            usleep(100000); // 100ms
        }

        if ($foundryData === null) {
            return response()->json([
                'message' => 'Foundry VTT did not respond in time.',
                'isOffline' => true
            ], 504);
        }

        return response()->json([
            'actor' => $foundryData['actor'] ?? null,
            'isOffline' => false
        ], 200);
    }
}
