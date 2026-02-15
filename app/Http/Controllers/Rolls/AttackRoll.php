<?php

namespace App\Http\Controllers\Rolls;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttackRoll extends FormRequest
{
    use HasConfiguration;

    public function attackRoll(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'selectedWeapon' => 'required|array|max:255',
            'selectedTarget' => 'required|array|max:255',
            'fireMode' => 'required|string|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $weaponId = $validatedData['selectedWeapon']["_id"];
        $targetId = $validatedData['selectedTarget']["id"];
        $fireMode = $validatedData['fireMode'];

        $client = $this->getFoundryClient();
        $user = auth()->user();

        $clientId = $user->getClientId();
        $actorId = $user->getActorId();

        //This JS may cause issues with targeting if there are many people trying to target.
        //The targeting from the dropdown is now removed.

        $js = "
            // --- CONFIGURATION ---
            const actorId = '$actorId';
            const weaponId = '$weaponId';
            const fireMode = '$fireMode'; // 'Single', 'Aim', 'Auto', 'Suppress'
                        const enemyTokenId = '$targetId';
                        const targetToken = canvas.tokens.get(enemyTokenId);

            // --- LOGIC ---
            const actor = game.actors.get(actorId);

            if (actor) {
                (async () => {
                    // 1. Construct the specific flag key for this weapon
                    const flagKey = 'firetype-' + weaponId;
                    const scope = 'cyberpunk-red-core';

                    let flagValue = ''; // Default is empty (Single shot)

                    // 2. Map your app's mode to the System's flag values
                    switch (fireMode) {
                        case 'Auto':
                            flagValue = 'autofire';
                            break;
                        case 'Suppress':
                            flagValue = 'suppressive';
                            break;
                        case 'Aimed':
                            flagValue = 'aimed';
                            // If aiming, we also ensure the location is set to head (default called shot)
                            await actor.setFlag(scope, 'aimedLocation', 'head');
                            break;
                        case 'Single':
                        default:
                            flagValue = ''; // Clear the flag for normal shooting
                            break;
                    }

                    targetToken.setTarget(true, {
                        releaseOthers: true,
                        groupSelection: true
                    });

                    // 3. Update the Actor's Flag
                    if (flagValue === '') {
                        // If Single, we unset the flag to clean up
                        await actor.unsetFlag(scope, flagKey);
                    } else {
                        // Otherwise set the mode
                        await actor.setFlag(scope, flagKey, flagValue);
                    }

                    // 4. Create the Fake Button for the System Logic
                    const dummyBtn = document.createElement('a');
                    dummyBtn.dataset.rollType = 'attack';
                    dummyBtn.dataset.itemId = weaponId;
                    dummyBtn.dataset.rollTitle = fireMode + ' Attack';

                    // 5. Execute the Roll
                    await actor.sheet._onRoll({
                        preventDefault: () => {},
                        currentTarget: dummyBtn,
                        type: 'click'
                    }, {
                        showDialog: false
                    });

                })();
            }
        ";



        CyberpunkAgentChannel::dispatch([
            'type' => 'ATTACK_ROLL',
            'actorId' => $actorId,
            'weaponId' => $weaponId,
            'fireMode' => $fireMode,
            'targetId' => $targetId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => null
        ], 201);
    }
}
