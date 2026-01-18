<?php

namespace App\Http\Controllers\Rolls;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramRoll extends FormRequest
{
    use HasConfiguration;

    public function programRoll(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'selectedWeapon' => 'required|array|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $weaponId = $validatedData['selectedWeapon']["_id"];

        $client = $this->getFoundryClient();
        $user = auth()->user();

        $clientId = $user->getClientId();
        $actorId = $user->getActorId();

        //This JS may cause issues with targeting if there are many people trying to target.
        //The targeting from the dropdown is now removed.
        //language=js
        $js = "
            // --- CONFIGURATION ---
            const actorId = '$actorId';
            const weaponId = '$weaponId';
            // --- LOGIC ---
            const actor = game.actors.get(actorId);

            if (actor) {
                (async () => {
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



        $return = $client->utilities->executeJs($clientId, \JShrink\Minifier::minify($js));

        if (isset($return["success"]) && $return["success"]) {
            //Attack has rolled, get the dice number
            $diceTotal = $client->roll->getLastRoll($clientId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $diceTotal
        ], 201);
    }
}
