<?php

namespace App\Http\Controllers\Data;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
        // Cache actor data briefly to reduce repeated calls when the sheet opens/rerenders
        $cacheKey = sprintf('actors:%s:%s', $clientId, $actorId);
        $ttl = now()->addSeconds(30);
        $character = Cache::remember($cacheKey, $ttl, function () use ($client, $clientId, $actorId) {
            return $client->entity->getEntity($clientId, $client->entity::UUID, "Actor.$actorId");
        });

        // Simplified for now, just returning the character data
        return response()->json($character, 200);
    }
}
