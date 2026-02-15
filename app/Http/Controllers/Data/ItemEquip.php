<?php

namespace App\Http\Controllers\Data;

use App\Http\HasConfiguration;
use App\Events\CyberpunkAgentChannel;
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
            'state' => 'required|string|in:owned,carried,equipped',
        ]);

        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();

        try {
            $client = $this->getFoundryClient();
            $itemId = $validatedData['itemId'];
            $stateString = $validatedData['state'];

            // Map strings directly for Foundry VTT CP Red system
            switch ($stateString) {
                case 'owned':
                case 'carried':
                case 'equipped':
                    $state = $stateString;
                    break;
                default:
                    $state = 'owned';
            };


            CyberpunkAgentChannel::dispatch([
                'type' => 'ITEM_EQUIP',
                'actorId' => $actorId,
                'itemId' => $itemId,
                'state' => $state,
            ]);

            return response()->json([
                'success' => true,
                'result' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
