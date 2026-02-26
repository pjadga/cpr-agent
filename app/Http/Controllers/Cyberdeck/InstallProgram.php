<?php

namespace App\Http\Controllers\Cyberdeck;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstallProgram extends FormRequest
{
    use HasConfiguration;

    public function installProgram(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'programId' => 'required|string|max:255',
            'deckId' => 'required|string|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $programId = $validatedData['programId'];
        $deckId = $validatedData['deckId'];

        $user = auth()->user();
        $actorId = $user->default_actor_id;
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'INSTALL_PROGRAM',
            'actorId' => $actorId,
            'programId' => $programId,
            'cyberdeckId' => $deckId,
            'requestId' => $requestId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => [
                'requestId' => $requestId,
            ],
        ], 201);
    }
}
