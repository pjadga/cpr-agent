<?php

namespace App\Http\Controllers\Values;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateStat extends FormRequest
{
    use HasConfiguration;

    public function updateStat(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'label' => 'required|string|max:255',
            'value' => 'required|int|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $label = strtolower($validatedData['label']);
        $value = $validatedData['value'];
        $client = $this->getFoundryClient();
        $return = $client->utilities->executeJs("foundry-xECODN3n8rO4E5Cj",
            "let updateStat = game.actors.getName('Jim');
            updateStat.update({'system.stats.$label.value': $value})");
        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
