<?php

namespace App\Http\Controllers\Rolls;

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
            'label' => 'required|string|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $label = ucfirst($validatedData['label']);

        $client = $this->getFoundryClient();
        $return = $client->utilities->executeJs("foundry-xECODN3n8rO4E5Cj",

        "let skillRoll = game.actors.getName('Jim');
        let id = skillRoll.items.getName('$label')._id;
        skillRoll.sheet._onRoll(
        {'data-roll-type': 'roleAbility',
        'data-roll-title': '$label',
        'data-item-id': id,
        'data-item-subtype': '$label',
        type: 'click',
        currentTarget: {'data-roll-type': 'roleAbility', 'data-roll-title': '$label', 'data-item-id': id, 'data-item-subtype': '$label' }})");

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
