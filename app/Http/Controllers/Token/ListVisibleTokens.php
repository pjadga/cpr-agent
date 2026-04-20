<?php

namespace App\Http\Controllers\Token;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ListVisibleTokens extends FormRequest
{
    use HasConfiguration;

    public function listVisibleTokens(): JsonResponse
    {
        $user = auth()->user();
        $tokenId = $user->getTokenId();
        $actorId = $user->getActorId();

        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'LIST_VISIBLE_TOKENS',
            'tokenId' => $tokenId,
            'actorId' => $actorId,
            'requestId' => $requestId,
        ]);

        // Polling loop
        $startTime = microtime(true);
        $timeout = 5.0; // Increased timeout
        $foundryData = null;

        while ((microtime(true) - $startTime) < $timeout) {
            $key = 'foundry_response_'.$requestId;
            if (Cache::has($key)) {
                $foundryData = Cache::pull($key);
                break;
            }
            usleep(20000); // 20ms
        }

        if ($foundryData === null) {
            return response()->json([
                'success' => false,
                'message' => 'Foundry VTT did not respond in time.',
            ], 504);
        }

        return response()->json([
            'success' => true,
            'message' => 'Visible tokens received',
            'requestId' => $requestId,
            'data' => $foundryData['tokens'] ?? [],
        ], 200);
    }
}
