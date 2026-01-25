<?php

namespace App\Http\Controllers\Rolls;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeathSave extends FormRequest
{
    use HasConfiguration;

    public function getDeathSave(Request $request): JsonResponse
    {
        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();
        $actorId = str_replace("Actor.", "", $actorId);
        $return = $client->utilities->executeJs($clientId,

        "let skillRoll = game.actors.get('$actorId');
        skillRoll.sheet._onRoll(
        {'data-roll-type': 'deathsave',
        'data-roll-title': 'Death Save',
        type: 'click',
        currentTarget: {'data-roll-type': 'deathsave', 'data-roll-title': 'Death Save'}})");

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
