<?php

namespace App\Http\Controllers\Rolls;

use App\Http\HasConfiguration;
use App\Support\Foundry\FoundryService;
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
        $return = $client->utilities->executeJs($clientId,

        "let skillRoll = game.actors.get('$actorId');
        let id = skillRoll.items.getName('$label')._id;
        skillRoll.sheet._onRoll(
        {'data-roll-type': 'skill',
        'data-roll-title': '$label',
        'data-item-id': id,
        type: 'click',
        currentTarget: {'data-roll-type': 'skill', 'data-roll-title': '$label', 'data-item-id': id}})");

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
