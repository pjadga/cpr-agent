<?php

namespace App\Http\Controllers\Rolls;

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

        $js = "const enemyTokenId = '$targetId';
                const targetToken = canvas.tokens.get(enemyTokenId);
                const actor = game.actors.get('$actorId');
                const actorId = actor.id;
                const weaponId = actor.items.get('$weaponId')._id
                let attackRoll;

                if (targetToken && actor) {
                    // 1. Set the target
                    targetToken.setTarget(true, { releaseOthers: true, groupSelection: true });
                    console.log('Successfully targeted: ' + targetToken.name);

                    attackRoll = actor.sheet._onRoll(
                    {'data-roll-type': 'attack',
                    'data-roll-title': 'Attack Roll',
                    'data-item-id': weaponId,
                    type: 'click',
                    currentTarget: {'data-roll-type': 'attack', 'data-roll-title': 'Attack Roll', 'data-item-id': weaponId}}
                    )

                    setTimeout(function(){
                        return attackRoll
                    }, 3000)
                }";

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
