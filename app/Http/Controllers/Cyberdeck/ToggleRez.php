<?php

namespace App\Http\Controllers\Cyberdeck;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ToggleRez extends FormRequest
{
    use HasConfiguration;

    public function toggleRez(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'programId' => 'required|string|max:255',
            'isRezzed' => 'required|bool|max:255',
        ]);

        // Need to get the actors either ID or Name -
        // The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $programId = $validatedData['programId'];
        $targetState = $validatedData['isRezzed'];

        $user = auth()->user();
        $actorId = $user->default_actor_id;
        $targetState = $targetState ? 1 : 0;
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'TOGGLE_REZ',
            'actorId' => $actorId,
            'programId' => $programId,
            'targetState' => $targetState,
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
