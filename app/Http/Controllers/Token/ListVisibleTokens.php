<?php

namespace App\Http\Controllers\Token;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ListVisibleTokens extends FormRequest
{
    use HasConfiguration;

    public function listVisibleTokens(): JsonResponse
    {
        $user = auth()->user();
        $tokenId = $user->getTokenId();

        //language=JS
        $js = "
        const PIXELS_PER_METER = canvas.grid.size;
        const tokenId = '$tokenId';
        const jim = canvas.tokens.get(tokenId);

        if (!jim) {
            console.error('Could not find token ' + tokenId + ' on scene.');
            return [];
        } else {
            const d = canvas.dimensions;
            const jimX = jim.center.x;
            const jimY = jim.center.y;

            const nearbyTokens = canvas.tokens.placeables
                .filter(t => {
                    const isVisible = !t.document.hidden;
                    const isActorType = t.actor && (t.actor.type === 'character' || t.actor.type === 'mook');
                    const isOnMap = t.x >= d.sceneX && t.x <= (d.sceneX + d.sceneWidth) &&
                        t.y >= d.sceneY && t.y <= (d.sceneY + d.sceneHeight);
                    return isVisible && isActorType && isOnMap && t.id !== jim.id;
                })
                .map(t => {
                    const distX = t.center.x - jimX;
                    const distY = t.center.y - jimY;
                    const distancePx = Math.sqrt(Math.pow(distX, 2) + Math.pow(distY, 2));
                    const distanceMeters = (distancePx / PIXELS_PER_METER).toFixed(2);
                    return {
                        name: t.name,
                        id: t.id,
                        distanceM: parseFloat(distanceMeters),
                        type: t.actor.type
                    };
                })
                .sort((a, b) => a.distanceM - b.distanceM);
                return nearbyTokens;
        }
        ";

        $nearbyTokens = CyberpunkAgentChannel::dispatch([
            'type' => 'LIST_VISIBLE_TOKENS',
            'tokenId' => $tokenId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visible tokens fetched successfully',
            'data' => $nearbyTokens
        ], 201);
    }
}
