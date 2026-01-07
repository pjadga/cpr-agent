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
                const weaponId = actor.items.get('$weaponId')._id

                if (targetToken && actor) {
                    // 1. Set the target
                    targetToken.setTarget(true, { releaseOthers: true, groupSelection: true });
                    console.log('Successfully targeted: ' + targetToken.name);

                    // This waits for the CPR system to broadcast that an attack roll is finished
                    Hooks.once('cyberpunk-red-core.isAttack', (rollData) => {
                        console.log('Attack roll detected. Total:', rollData.resultTotal);

                        // 3. Only untarget if the roll actually happened (total > 0)
                        if (rollData.resultTotal && rollData.resultTotal > 0) {
                            targetToken.setTarget(false);
                            console.log('Roll complete: Target cleared.');
                        }
                    });

                    actor.sheet._onRoll(
        {'data-roll-type': 'attack',
        'data-roll-title': 'Attack Roll',
        'data-item-id': weaponId,
        type: 'click',
        currentTarget: {'data-roll-type': 'attack', 'data-roll-title': 'Attack Roll', 'data-item-id': weaponId}})
                }";

        $oldJs = "
        const enemyTokenId = '$targetId'; // This comes from your enemy list
        const targetToken = canvas.tokens.get(enemyTokenId);
        if (targetToken) {
            targetToken.setTarget(true, { releaseOthers: true, groupSelection: true });
            console.log('Successfully targeted: ' + targetToken.name);
        }

        let attackRoll = game.actors.get('$actorId');
        let id = attackRoll.items.get('$weaponId')._id;
        attackRoll.sheet._onRoll(
        {'data-roll-type': 'attackl',
        'data-roll-title': 'Attack Roll',
        'data-item-id': id,
        type: 'click',
        currentTarget: {'data-roll-type': 'attack', 'data-roll-title': 'Attack Roll', 'data-item-id': id}})

        targetToken.setTarget(false, { releaseOthers: true, groupSelection: true });
        ";


        $return = $client->utilities->executeJs($clientId, $js);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
