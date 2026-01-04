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

    /*
     * This is creating the problem of ownership. Each user will need their own Client ID - which is linked to their foundry instance.
     * The Client ID, sans "foundry-" is the ID. On this load, ideally, the user can log in, the same as foundry. In reality - these ID's will need to be
     * assigned manually in something akin to the configuration. It's not as good as a login, but only needs to be done once.
     * That said, this takes a while - so the User flow is
     *
     * Load app, Log on if needed to (to laravel)
     * After setting up their ClientId, they will be able to see what Actors are owned by then using the script below.
     * They can then save the Actor ID to their profile, allowing it to be loaded when ever.
     */

    public function getActors(Request $request)
    {
        $client = $this->getFoundryClient();
        $search = $client->searchFor->searchEntities("foundry-xECODN3n8rO4E5Cj", "Jim", "documentType:Actor");
        $characterIds = [];
        foreach ($search['results'] as $character) {
            $characterIds[] = $character['uuid'];
        }
        foreach($characterIds as $characterId) {
            $character = $client->entity->getEntity("foundry-xECODN3n8rO4E5Cj", $client->entity::UUID, $characterId);
            if (isset($character["data"]["ownership"]['bif1vMjwqH7Cr1dq'])){ //NOTE THE ID HAS CHANGED TO A USER
                $data = $character;
            } else {
                $data = false;
            }
        }

        return response()->json($data, 200);
    }
}
