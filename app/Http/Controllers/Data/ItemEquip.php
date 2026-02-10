<?php

namespace App\Http\Controllers\Data;

use App\Http\HasConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ItemEquip extends Controller
{
    use HasConfiguration;

    /**
     * Update the equipment state of an item.
     * State mapping usually: 0 = Owned, 1 = Carried, 2 = Equipped (Foundry VTT CP Red)
     */
    public function updateState(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'itemId' => 'required|string',
            'state' => 'required|integer|in:0,1,2',
        ]);

        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();

        try {
            $client = $this->getFoundryClient();
            $itemId = $validatedData['itemId'];
            $state = $validatedData['state'];

            $js = "
                const actor = game.actors.get('$actorId');
                if (actor) {
                    const item = actor.items.get('$itemId');
                    if (item) {
                        await item.update({'system.equipped': $state});
                        return true;
                    }
                }
                return false;
            ";

            $result = $client->utilities->executeJs($clientId, $js);

            return response()->json([
                'success' => true,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
