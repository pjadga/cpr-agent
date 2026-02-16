<?php

namespace App\Http\Controllers\Token;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
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

        CyberpunkAgentChannel::dispatch([
            'type' => 'GET_DV_INFO',
            'tokenId' => $tokenId,
            'weaponId' => $weaponId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'DV info request sent to Foundry',
            'data' => null,
        ], 201);
    }
}
