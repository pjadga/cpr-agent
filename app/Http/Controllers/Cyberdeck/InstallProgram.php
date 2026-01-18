<?php

namespace App\Http\Controllers\Cyberdeck;

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
        $client = $this->getFoundryClient();

        $user = auth()->user();
        $clientId = $user->client_id;
        $actorId = $user->default_actor_id;

        //language=js
        $js = "
            const actor = game.actors.get('$actorId');
            if (!actor) return { error: 'Actor not found' };

            const deck = actor.items.get('$deckId');
            const program = actor.items.get('$programId');

            if (!deck || !program) return { error: 'Item not found' };

            // Check capacity
            const currentSlots = deck.system.installedItems.usedSlots || 0;
            const maxSlots = deck.system.installedItems.slots || 0;
            const progSize = program.system.size || 1;

            if (currentSlots + progSize > maxSlots) {
                return { error: 'Not enough memory slots' };
            }

            // Update the list
            const currentList = deck.system.installedItems.list || [];
            // Prevent duplicate install of same item instance ID
            if (currentList.includes('$programId')) return { success: true };

            const newList = [...currentList, '$programId'];

            await deck.update({
                'system.installedItems.list': newList,
                'system.installedItems.usedSlots': currentSlots + progSize
            });

            return { success: true };
        ";

        $return = $client->utilities->executeJs($clientId, $js);
        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
