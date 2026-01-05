<?php

namespace App\Http\Controllers\Data;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetActors extends FormRequest
{
    use HasConfiguration;

    public function getActors(Request $request)
    {
        $client = $this->getFoundryClient();
        $user = auth()->user();

        // Use user's client_id if available, otherwise fallback to a default or dummy for testing
        $clientId = $user->client_id ?? "foundry-xECODN3n8rO4E5Cj";
        $actorId = $user->default_actor_id ?? "Actor.BehNGodGRTaG7ClI";

        $character = $client->entity->getEntity($clientId, $client->entity::UUID, $actorId);

        // Simplified for now, just returning the character data
        return response()->json($character, 200);
    }
}
