<?php

namespace App\Http\Controllers\Rolls;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleRollRequest extends FormRequest
{
    use HasConfiguration;

    public function rollRole(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'label' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'isAbility' => 'required|boolean'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $label = ucfirst($validatedData['label']); //Will also be role, but could be abilities
        $role = ucfirst($validatedData['role']); //Top  level role name

        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();
        $actorId = str_replace("Actor.", "", $actorId);

        CyberpunkAgentChannel::dispatch([
            'type' => 'ROLE_ROLL',
            'actorId' => $actorId,
            'itemName' => ($validatedData['isAbility'] ? $role : $label),
            'label' => $label,
            'isAbility' => $validatedData['isAbility'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => null
        ], 201);
    }
}
