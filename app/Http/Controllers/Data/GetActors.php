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
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();
        $character = $client->entity->getEntity($clientId, $client->entity::UUID, "Actor.$actorId");

        // Simplified for now, just returning the character data
        return response()->json($character, 200);
    }
}
