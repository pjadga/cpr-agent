<?php

namespace App\Http\Controllers\Cyberdeck;

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
            'isRezzed' => 'required|bool|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $programId = $validatedData['programId'];
        $targetState = $validatedData['isRezzed'];
        $client = $this->getFoundryClient();

        $user = auth()->user();
        $clientId = $user->client_id;
        $actorId = $user->default_actor_id;

        //language=js
        $js = "
            const programId = '$programId';
            const targetState = $targetState;

            const actor = game.actors.get($actorId);
            if (!actor) return { error: 'Actor not found' };

            const program = actor.items.get(programId);
            if (!program) return { error: 'Program not found' };

            // Update the isRezzed boolean in the system data
            await program.update({
                'system.isRezzed': targetState
            });

            return { success: true, isRezzed: targetState };
        ";

        $return = $client->utilities->executeJs($clientId, $js);
        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
