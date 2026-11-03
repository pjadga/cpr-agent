<?php

namespace App\Http\Controllers\Token;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GetVisibleTokens extends FormRequest
{
    use HasConfiguration;

    public function getVisibleTokens(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'selectedWeapon' => 'required|array|max:255',
            'selectedTarget' => 'nullable|array|max:255',
            // Single, Aimed, Auto or Suppress: autofire has its own DV table.
            'fireMode' => 'nullable|string|max:20',
        ]);

        $user = auth()->user();
        $tokenId = $user->getTokenId();
        $actorId = $user->getActorId();
        $weaponId = $validatedData['selectedWeapon']['_id'] ?? null;
        $targetTokenId = $validatedData['selectedTarget']['id'] ?? null;

        if (! $weaponId) {
            return response()->json([
                'success' => false,
                'message' => 'Weapon ID not provided',
            ], 422);
        }

        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'GET_DV_INFO',
            'tokenId' => $tokenId,
            'actorId' => $actorId,
            'weaponId' => $weaponId,
            'targetTokenId' => $targetTokenId,
            'fireMode' => $validatedData['fireMode'] ?? null,
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
            'message' => 'DV info received',
            'requestId' => $requestId,
            'data' => $foundryData['result'] ?? null,
        ], 200);
    }
}
