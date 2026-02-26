<?php

namespace App\Http\Controllers\Token;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReloadWeapon extends FormRequest
{
    use HasConfiguration;

    public function reloadWeapon(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'weaponId' => 'required|string',
            'ammoId' => 'nullable|string',
            'actorId' => 'nullable|string',
            'tokenId' => 'nullable|string',
        ]);

        $weaponId = $validatedData["weaponId"];
        $ammoId = $validatedData["ammoId"] ?? null;
        $user = auth()->user();
        $actorId = $validatedData['actorId'] ?? $user->getActorId();
        $tokenId = $validatedData['tokenId'] ?? $user->getTokenId();
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'RELOAD_WEAPON',
            'actorId' => $actorId,
            'tokenId' => $tokenId,
            'weaponId' => $weaponId,
            'ammoId' => $ammoId,
            'requestId' => $requestId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => [
                'requestId' => $requestId,
            ],
        ], 201);
    }
}
