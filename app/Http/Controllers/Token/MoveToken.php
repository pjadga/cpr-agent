<?php

namespace App\Http\Controllers\Token;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class MoveToken extends FormRequest
{
    use HasConfiguration;

    public function moveToken(Request $request)
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'direction' => 'required|string|max:255',
            'weaponId' => 'nullable|string|max:255',
            'actorId' => 'nullable|string|max:255',
            'tokenId' => 'nullable|string|max:255',
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $direction = $validatedData['direction'];
        $weaponId = $validatedData['weaponId'] ?? null;
        $user = auth()->user();
        $tokenId = $validatedData['tokenId'] ?? $user->getTokenId();
        $actorId = $validatedData['actorId'] ?? $user->getActorId();
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'MOVE_TOKEN',
            'tokenId' => $tokenId,
            'actorId' => $actorId,
            'direction' => $direction,
            'weaponId' => $weaponId,
            'requestId' => $requestId,
        ]);

        return response()->json([
            'success' => true,
            'requestId' => $requestId,
        ], 200);
    }
}
