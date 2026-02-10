<?php

namespace App\Http\Controllers\Token;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
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
            'selectedWeapon' => 'required|array',
            'ammoId' => 'nullable|string'
        ]);

        $weaponId = $validatedData["selectedWeapon"]["_id"];
        $ammoId = $validatedData["ammoId"] ?? null;
        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $actorId = $user->getActorId();

        //language=js
        $js = "
        const actor = game.actors.get('$actorId');
        const weapon = actor.items.get('$weaponId');
        const selectedAmmo = " . ($ammoId ? "actor.items.get('$ammoId')" : "null") . ";
        if (selectedAmmo) {
            let currentAmmo = weapon.getInstalledItems('ammo');
            if (currentAmmo && currentAmmo.length > 0) {
                weapon.uninstallItems(currentAmmo);
            }
            setTimeout(function(){
                weapon.installItems([selectedAmmo]);
            },500)

            setTimeout(function(){
                weapon.reload();
            },1500)
        } else {
            weapon.reload();
        }
        ";

        $return = $client->utilities->executeJs($clientId, $js);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
