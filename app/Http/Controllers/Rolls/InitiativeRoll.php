<?php
namespace App\Http\Controllers\Rolls;

use App\Events\CyberpunkAgentChannel;
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

        $user = auth()->user();
        $actorId = $user->getActorId();
        $actorId = str_replace("Actor.", "", $actorId);

        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'INITIATIVE_ROLL',
            'actorId' => $actorId,
            'requestId' => $requestId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Initiative rolled successfully!',
            'data' => [
                'requestId' => $requestId
            ],
        ], 201);
    }
}
