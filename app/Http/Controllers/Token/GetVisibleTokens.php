<?php

namespace App\Http\Controllers\Token;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
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

        $js = "const d=canvas.dimensions;const validTokens=canvas.tokens.placeables.filter(t=>{const d=canvas.dimensions;const isVisible=!t.document.hidden;const isActorType=t.actor&&(t.actor.type==='character'||t.actor.type==='mook');const isOnMap=t.x>=d.sceneX&&t.x<=(d.sceneX+d.sceneWidth)&&t.y>=d.sceneY&&t.y<=(d.sceneY+d.sceneHeight);return isVisible&&isActorType&&isOnMap}).map(t=>({name:t.name,id:t.id,actorId:t.actor.id,type:t.actor.type,x:Math.round(t.x),y:Math.round(t.y)}));return validTokens";
        $return = $client->utilities->executeJs($clientId, $js);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
