<?php

namespace App\Http\Controllers\Data;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GetRollModifiers extends FormRequest
{
    use HasConfiguration;

    public function getRollModifiers(Request $request)
    {
        $user = auth()->user();

        $validatedData = $request->validate([
            'selectedWeapon' => 'nullable|array|max:255',
        ]);

        // Use user's client_id if available, otherwise fallback to a default or dummy for testing
        $actorId = $user->getActorId();
        $weaponId = isset($validatedData['selectedWeapon']['_id']) ? $validatedData['selectedWeapon']['_id'] : null;

        CyberpunkAgentChannel::dispatch([
            'type' => 'GET_ROLL_MODIFIERS',
            'actorId' => $actorId,
            'weaponId' => $weaponId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Roll modifiers request sent to Foundry',
            'data' => null
        ], 201);
    }
}
