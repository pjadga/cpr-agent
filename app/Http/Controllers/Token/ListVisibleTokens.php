<?php

namespace App\Http\Controllers\Token;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Str;

class ListVisibleTokens extends FormRequest
{
    use HasConfiguration;

    public function listVisibleTokens(): JsonResponse
    {
        $user = auth()->user();
        $tokenId = $user->getTokenId();

        $requestId = (string) Str::uuid();

        CyberpunkAgentChannel::dispatch([
            'type' => 'LIST_VISIBLE_TOKENS',
            'tokenId' => $tokenId,
            'requestId' => $requestId,
        ]);

        // Polling loop
        $startTime = microtime(true);
        $timeout = 3.0;
        $foundryData = null;

        while ((microtime(true) - $startTime) < $timeout) {
            $key = 'foundry_response_' . $requestId;
            if (Cache::has($key)) {
                $foundryData = Cache::pull($key);
                break;
            }
            usleep(100000);
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
            'data' => $foundryData['tokens'] ?? [],
        ], 200);
    }
}
