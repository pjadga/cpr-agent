<?php

namespace App\Http\Controllers\Data;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetActors extends FormRequest
{
    use HasConfiguration;

    public function getActors(Request $request)
    {
        $client = $this->getFoundryClient();
//        $search = $client->searchFor->searchEntities("foundry-xECODN3n8rO4E5Cj", "", "documentType:Actor");
//        $characterIds = [];
//        foreach ($search['results'] as $character) {
//            $characterIds[] = $character['uuid'];
//        }
        //foreach($characterIds as $characterId) {
            $character = $client->entity->getEntity("foundry-xECODN3n8rO4E5Cj", $client->entity::UUID, "Actor.BehNGodGRTaG7ClI");
            if (isset($character["data"]["ownership"]['yMtjHtgJj3VENELV'])){ //NOTE THE ID HAS CHANGED TO A USER
                $data = $character;
            } else {
                $data = false;
            }
        //}

        return response()->json($data, 200);
    }
}
