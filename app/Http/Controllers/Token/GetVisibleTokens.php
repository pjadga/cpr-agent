<?php

namespace App\Http\Controllers\Token;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use JShrink\Minifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GetVisibleTokens extends FormRequest
{
    use HasConfiguration;

    public function getVisibleTokens(Request $request): JsonResponse
    {

        // Validate incoming request
        $validatedData = $request->validate([
            'selectedWeapon' => 'required|array|max:255'
        ]);

        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $tokenId = $user->getTokenId();
        // Expect full weapon object and extract the ID for Foundry lookup
        $weaponId = $validatedData['selectedWeapon']["_id"] ?? null;
        if (!$weaponId) {
            return response()->json([
                'success' => false,
                'message' => 'Weapon ID not provided',
                'data' => null,
            ], 422);
        }
        //language=JS
        $js = "
            const RANGE_DV_TABLE = {
              'Pistol':           [13, 15, 20, 25, 30, 30, 99, 99],
              'SMG':              [15, 13, 15, 20, 25, 25, 30, 99],
              'Shotgun (Slug)':   [13, 15, 20, 25, 30, 35, 99, 99],
              'Assault Rifle':    [17, 16, 15, 13, 15, 20, 25, 30],
              'Sniper Rifle':     [30, 25, 25, 20, 15, 16, 17, 20],
              'Bows & Crossbow':  [15, 13, 15, 17, 20, 22, 99, 99],
              'Grenade Launcher': [16, 15, 15, 17, 20, 22, 25, 99],
              'Rocket Launcher':  [17, 16, 15, 15, 20, 20, 25, 30],
              'Martial Arts': ['Ka Pow'],
              'Unarmed': ['unarmed']
            };
            const DISTANCE_BRACKETS = [6, 12, 25, 50, 100, 200, 400, 800];

            const jim = canvas.tokens.placeables.find(t => t.id === '$tokenId')
            const target = Array.from(game.user.targets)[0];

            if (jim && target) {
                // Distance Calculation (128px = 1m)
                const distancePx = Math.sqrt(Math.pow(target.center.x - jim.center.x, 2) + Math.pow(target.center.y - jim.center.y, 2));
                const distanceM = Math.ceil(distancePx / 128);

                // Weapon Mapping
                const weaponData = jim.actor.items.get('$weaponId');
                const typeMap = {
                    'heavyPistol': 'Pistol',
                    'assaultRifle': 'Assault Rifle',
                    'smg': 'SMG',
                    'shotgun': 'Shotgun (Slug)',
                    'sniperRifle': 'Sniper Rifle',
                    'unarmed' : 'Unarmed',
                    'martialArts' : 'Martial Arts',
                    'grenade' : 'Grenade Launcher',
                    'thrownWeapon': 'Grenade Launcher',
                };
                const category = (typeMap[weaponData.system.weaponType] || typeMap[weaponData.system.variety]) || 'Pistol';

                // Find DV
                const bracketIndex = DISTANCE_BRACKETS.findIndex(limit => distanceM <= limit);
                const targetDV = bracketIndex !== -1 ? RANGE_DV_TABLE[category][bracketIndex] : 99;

                const result = {
                    targetName: target.name,
                    distanceMeters: distanceM,
                    weaponCategory: category,
                    dv: targetDV === 99 ? 'Out of Range' : targetDV
                };

                console.log('TARGET_DV_PACKET:', JSON.stringify(result));
                return result;
            }
            return { error: 'No valid token/target' };
        ";

        // Cache very briefly because distance/target may change rapidly; still reduces spam on quick UI flips
        $cacheKey = sprintf('dvinfo:%s:%s:%s', $clientId, $tokenId, $weaponId);
        $ttl = now()->addSeconds(1);
        $return = Cache::remember($cacheKey, $ttl, function () use ($client, $clientId, $js) {
            return $client->utilities->executeJs($clientId, \JShrink\Minifier::minify($js));
        });

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
