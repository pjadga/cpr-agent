<?php

namespace App\Http\Controllers\Token;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use JShrink\Minifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetVisibleTokens extends FormRequest
{
    use HasConfiguration;

    public function getVisibleTokens(Request $request): JsonResponse
    {
        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $tokenId = $user->getTokenId();
        $js = "
        const PIXELS_PER_METER = canvas.grid.size;
        const tokenId = '$tokenId';//Saved Token ID
        const jim = canvas.tokens.placeables.find(t => t.id === tokenId); //RIP Jim

        if (!jim) {
            console.error(`Could not find a token on scene.`);
            return 'Nope!!!'
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
                    const distancePx = Math.sqrt(distX**2 + distY**2);
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
