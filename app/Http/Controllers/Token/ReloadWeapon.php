<?php

namespace App\Http\Controllers\Token;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use JShrink\Minifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReloadWeapon extends FormRequest
{
    use HasConfiguration;

    public function reloadWeapon(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'selectedWeapon' => 'required|array|max:255'
        ]);

        $weaponId = $validatedData['selectedWeapon']["_id"];
        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();

        $js = "
        const actor = game.actors.get('$actorId');
        const weapon = actor.items.get('$weaponId')
        weapon.reload();
        ";

        $return = $client->utilities->executeJs($clientId,
            \JShrink\Minifier::minify($js)
        );

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
