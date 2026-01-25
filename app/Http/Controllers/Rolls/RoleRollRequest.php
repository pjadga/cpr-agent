<?php

namespace App\Http\Controllers\Rolls;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleRollRequest extends FormRequest
{
    use HasConfiguration;

    public function rollRole(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'label' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'isAbility' => 'required|boolean'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $label = ucfirst($validatedData['label']); //Will also be role, but could be abilities
        $role = ucfirst($validatedData['role']); //Top  level role name

        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();
        $actorId = str_replace("Actor.", "", $actorId);

        if ($validatedData['isAbility']) {
            $js = "let skillRoll = game.actors.get('$actorId');
        let id = skillRoll.items.getName('$role')._id;
        let roll = skillRoll.sheet._onRoll(
        {
        'data-roll-type': 'roleAbility',
        'data-roll-subtype': 'subRoleAbility',
        'data-roll-title': '$label',
        'data-role-name': '$label',
        'data-item-id': id,
        'data-item-subtype': '$label',
        type: 'click',
        currentTarget: {
        'data-roll-type': 'roleAbility',
        'data-roll-subtype': 'subRoleAbility',
         'data-roll-title': '$label',
        'data-item-id': id,
        'data-role-name': '$label',
        'data-item-subtype': '$label'
        }});return skillRoll";
        } else {
            $js = "let skillRoll = game.actors.get('$actorId');
        let id = skillRoll.items.getName('$label')._id;
        skillRoll.sheet._onRoll(
        {'data-roll-type': 'roleAbility',
        'data-roll-title': '$label',
        'data-item-id': id,
        'data-item-subtype': '$label',
        type: 'click',
        currentTarget: {'data-roll-type': 'roleAbility', 'data-roll-title': '$label',
        'data-item-id': id, 'data-item-subtype': '$label' }});return skillRoll";
        }



        $return = $client->utilities->executeJs($clientId, $js);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return
        ], 201);
    }
}
