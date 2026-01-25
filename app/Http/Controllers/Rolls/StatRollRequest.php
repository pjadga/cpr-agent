<?php

namespace App\Http\Controllers\Rolls;

use App\Http\HasConfiguration;
use App\Support\Foundry\FoundryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatRollRequest extends FormRequest
{
    use HasConfiguration;

    public function rollStat(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'label' => 'required|string|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $label = strtolower($validatedData['label']);
        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();
        $actorId = str_replace("Actor.", "", $actorId);

        $return = $client->utilities->executeJs($clientId,
        "let statRoll = game.actors.get('$actorId');
        statRoll.sheet._onRoll(
        {'data-roll-type': 'stat',
        'data-roll-title': '$label',
        type: 'click',
        currentTarget: {'data-roll-type': 'stat', 'data-roll-title': '$label'}})");
        // Save the data (example)
        // Message::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
