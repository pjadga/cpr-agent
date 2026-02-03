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
        $user = auth()->user();
        $actorId = $user->getActorId();

        try {
            $client = $this->getFoundryClient();
            $clientId = $user->getClientId();
            // Cache actor data briefly to reduce repeated calls when the sheet opens/rerenders
            $cacheKey = sprintf('actors:%s:%s', $clientId, $actorId);
            $ttl = now()->addSeconds(30);
            $character = Cache::remember($cacheKey, $ttl, function () use ($client, $clientId, $actorId) {
                return $client->entity->getEntity($clientId, $client->entity::UUID, "Actor.$actorId");
            });

            // Simplified for now, just returning the character data
            $character['uuid'] = "Actor.$actorId";
            return response()->json($character, 200);
        } catch (\Exception $e) {
            // If offline, return a dummy response with the actorUuid so the frontend can still call /actorsheet
            return response()->json([
                'uuid' => "Actor." . ($actorId ?: 'demo'),
                'isOffline' => true
            ], 200);
        }
    }
}
