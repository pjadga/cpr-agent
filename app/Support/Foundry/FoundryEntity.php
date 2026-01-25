<?php

namespace App\Support\Foundry;

use GuzzleHttp\Client;

class FoundryEntity
{
    const UUID = 'uuid';

    public function __construct(protected Client $client) {}

    public function getActorSheetAsJson(string $clientId, string $actorUuid)
    {
        $response = $this->client->post('cpra-entry', [
            'json' => [
                'action' => 'getActorSheetAsJson',
                'clientId' => $clientId,
                'actorUuid' => $actorUuid,
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    public function getEntity(string $clientId, string $type, string $id)
    {
        $response = $this->client->post('cpra-entry', [
            'json' => [
                'action' => 'getEntity',
                'clientId' => $clientId,
                'type' => $type,
                'id' => $id,
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
