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
            'selectedWeapon' => 'required|array',
            'ammoId' => 'nullable|string'
        ]);

        $weaponId = $validatedData["selectedWeapon"]["_id"];
        $ammoId = $validatedData["ammoId"] ?? null;
        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'RELOAD_WEAPON',
            'actorId' => $actorId,
            'weaponId' => $weaponId,
            'ammoId' => $ammoId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => null,
        ], 201);
    }
}
