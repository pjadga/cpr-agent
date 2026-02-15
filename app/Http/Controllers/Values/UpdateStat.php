<?php

namespace App\Http\Controllers\Values;

use App\Events\CyberpunkAgentChannel;
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

        if ($label === "hit points" || $label === "humanity") {
            $label = "derivedStats" . ($label === "hit points" ? ".hp" : ".humanity") . ".value";
        } else {
            $label = "stats." . $label . ".value";
        }

        $user = auth()->user();
        $clientId = $user->client_id ?? "foundry-xECODN3n8rO4E5Cj";
        $actorId = $user->default_actor_id ?? "Actor.BehNGodGRTaG7ClI";
        $actorId = str_replace("Actor.", "", $actorId);

        CyberpunkAgentChannel::dispatch([
            'type' => 'UPDATE_STAT',
            'actorId' => $actorId,
            'label' => $label,
            'value' => $value,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => null,
        ], 201);
    }
}
