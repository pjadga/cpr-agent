<?php
namespace App\Http\Controllers\Rolls;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InitiativeRoll extends FormRequest
{
    use HasConfiguration;

    public function rollInitiative(Request $request): JsonResponse
    {
        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();
        $actorId = str_replace("Actor.", "", $actorId);
        $return = $client->utilities->executeJs($clientId,
        "let skillRoll = game.actors.get('$actorId').rollInitiative();");

        return response()->json([
            'success' => true,
            'message' => 'Initiative rolled successfully!',
            'data' => $return,
        ], 201);
    }
}
