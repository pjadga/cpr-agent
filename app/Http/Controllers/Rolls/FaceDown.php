<?php

namespace App\Http\Controllers\Rolls;

use App\Http\HasConfiguration;
use App\Support\Foundry\FoundryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceDown extends FormRequest
{
    use HasConfiguration;

    public function getFaceDown(Request $request): JsonResponse
    {
        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();
        $actorId = str_replace("Actor.", "", $actorId);
        $return = $client->utilities->executeJs($clientId,

        "let skillRoll = game.actors.get('$actorId');
        skillRoll.sheet._onRoll(
        {'data-roll-type': 'facedown',
        'data-roll-title': 'Face Down',
        type: 'click',
        currentTarget: {'data-roll-type': 'facedown', 'data-roll-title': 'Face Down'}})");

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
