<?php

namespace App\Http\Controllers\Rolls;

use App\Http\HasConfiguration;
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
            'programName' => 'string|max:255',
            'programId' => 'string|max:255',
            'type' => 'string|max:255',
            'executionType' => 'string|max:255',
            'cyberdeckId' => 'string|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $programName = $validatedData['programName']  ?? null;
        $programId = $validatedData['programId']  ?? null;
        $cyberdeckId = $validatedData['cyberdeckId']  ?? null;
        $executionType = $validatedData['executionType']  ?? null;
        $type = $validatedData['type']  ?? null;

        $client = $this->getFoundryClient();
        $user = auth()->user();

        $clientId = $user->getClientId();
        $actorId = $user->getActorId();

        //This JS may cause issues with targeting if there are many people trying to target.
        //The targeting from the dropdown is now removed.
        //language=js



        $js = "
        let skillRoll = game.actors.get('$actorId');
        skillRoll.sheet._onRoll(
        {
        type: 'click',
        currentTarget: {
        'data-roll-type': 'interfaceAbility',
        'data-interface-ability': '$programName',
        'data-cyberdeck-id': '$cyberdeckId'
        }})
        ";
        $return = $client->utilities->executeJs($clientId, $js);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return
        ], 201);
    }
}
