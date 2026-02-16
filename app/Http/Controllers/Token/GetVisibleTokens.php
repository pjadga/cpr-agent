<?php

namespace App\Http\Controllers\Token;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Str;

class GetVisibleTokens extends FormRequest
{
    use HasConfiguration;

    public function getVisibleTokens(Request $request): JsonResponse
    {

        // Validate incoming request
        $validatedData = $request->validate([
            'selectedWeapon' => 'required|array|max:255'
        ]);

        $user = auth()->user();
        $tokenId = $user->getTokenId();
        // Expect full weapon object and extract the ID for Foundry lookup
        $weaponId = $validatedData['selectedWeapon']["_id"] ?? null;
        if (!$weaponId) {
            return response()->json([
                'success' => false,
                'message' => 'Weapon ID not provided',
                'data' => null,
            ], 422);
        }

        $requestId = (string) Str::uuid();

        CyberpunkAgentChannel::dispatch([
            'type' => 'GET_DV_INFO',
            'tokenId' => $tokenId,
            'weaponId' => $weaponId,
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
            'message' => 'DV info received',
            'data' => $foundryData['result'] ?? null,
        ], 200);
    }
}
