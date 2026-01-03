<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use FoundryClient\FoundryClient;

class StatRollRequest extends FormRequest
{

    public function rollStat(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'label' => 'required|string|max:255',
            'value' => 'required|int|max:255'
        ]);

        $client = new FoundryClient("test-api-key", "https://foundry-api.surrealdisco.co.uk");
        $return = $client->utilities->js("foundry-xECODN3n8rO4E5Cj",
        "let Jim = game.actors.getName('Jim'); let id = Jim.items.getName('Accounting')._id; Jim.sheet._onRoll( { 'data-roll-type': 'skill', 'data-roll-title': 'accounting', 'data-item-id': id, type: 'click', currentTarget: {'data-roll-type': 'skill', 'data-roll-title': 'accounting', 'data-item-id': id} })");
        // Save the data (example)
        // Message::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
