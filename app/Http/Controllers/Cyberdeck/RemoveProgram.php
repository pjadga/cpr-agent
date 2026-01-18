<?php

namespace App\Http\Controllers\Cyberdeck;

use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoveProgram extends FormRequest
{
    use HasConfiguration;

    public function removeProgram(Request $request): JsonResponse
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

            if (!deck) return { error: 'Deck not found' };

            const currentList = deck.system.installedItems.list || [];
            if (!currentList.includes('$programId')) return { success: true };

            // Remove from list
            const newList = currentList.filter(id => id !== '$programId');

            // Calculate size (default to 1 if program missing for some reason)
            const progSize = program ? (program.system.size || 1) : 1;
            const currentSlots = deck.system.installedItems.usedSlots || 0;

            // Ensure slots don't go below 0
            const newSlots = Math.max(0, currentSlots - progSize);

            await deck.update({
                'system.installedItems.list': newList,
                'system.installedItems.usedSlots': newSlots
            });

            // Optional: Ensure program is Derezed when uninstalled
            if (program && program.system.isRezzed) {
                await program.update({ 'system.isRezzed': false });
            }

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
