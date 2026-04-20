<?php

namespace App\Http\Controllers\Rolls;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DamageRoll extends FormRequest
{
    use HasConfiguration;

    public function damageRoll(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'selectedWeapon' => 'required|array|max:255',
            'selectedTarget' => 'required|array|max:255',
            'fireMode' => 'required|string|max:255',
            'actorId' => 'nullable|string|max:255',
            'tokenId' => 'nullable|string|max:255',
            'rollMode' => 'nullable|string',
        ]);

        // Need to get the actors either ID or Name -
        // The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $weaponId = $validatedData['selectedWeapon']['_id'];
        $targetId = $validatedData['selectedTarget']['id'];
        $fireMode = $validatedData['fireMode'];

        $user = auth()->user();
        $actorId = $validatedData['actorId'] ?? $user->getActorId();
        $tokenId = $validatedData['tokenId'] ?? $user->getTokenId();
        // TODO Aimed, damage types
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'DAMAGE_ROLL',
            'actorId' => $actorId,
            'tokenId' => $tokenId,
            'weaponId' => $weaponId,
            'targetId' => $targetId,
            'rollMode' => $validatedData['rollMode'] ?? null,
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
