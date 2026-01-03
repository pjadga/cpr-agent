<?php

namespace App\Http\Controllers\Rolls;

use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatRollRequest extends FormRequest
{

    public function rollStat(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'label' => 'required|string|max:255',
            'value' => 'required|int|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $label = strtolower($validatedData['label']);

        $client = new FoundryClient("test-api-key", "https://foundry-api.surrealdisco.co.uk");
        $return = $client->utilities->js("foundry-xECODN3n8rO4E5Cj",
        "let statRoll = game.actors.getName('Jim');
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
