<?php

namespace App\Http\Controllers\Rolls;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillRollRequest extends FormRequest
{
    use HasConfiguration;

    public function rollSkill(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'label' => 'required|string|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $label = ucfirst($validatedData['label']);

        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();
        $actorId = str_replace("Actor.", "", $actorId);
        CyberpunkAgentChannel::dispatch([
            'type' => 'SKILL_ROLL',
            'actorId' => $actorId,
            'label' => $label,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => null,
        ], 201);
    }
}
