<?php

namespace App\Http\Controllers\Data;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class GetRollModifiers extends FormRequest
{
    use HasConfiguration;

    public function getRollModifiers(Request $request)
    {
        $user = auth()->user();

        $validatedData = $request->validate([
            'selectedWeapon' => 'nullable|array|max:255',
        ]);

        // Use user's default_actor_id
        $actorId = $user->getActorId();
        $weaponId = isset($validatedData['selectedWeapon']['_id']) ? $validatedData['selectedWeapon']['_id'] : null;
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'GET_ROLL_MODIFIERS',
            'actorId' => $actorId,
            'weaponId' => $weaponId,
            'requestId' => $requestId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Roll modifiers request sent to Foundry',
            'requestId' => $requestId,
            'data' => null,
        ], 201);
    }
}
