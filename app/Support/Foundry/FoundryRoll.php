<?php

namespace App\Support\Foundry;

use GuzzleHttp\Client;

class FoundryRoll
{
    public function __construct(protected Client $client) {}

    public function getLastRoll(string $clientId)
    {
        $response = $this->client->post('cpra-entry', [
            'json' => [
                'action' => 'getLastRoll',
                'clientId' => $clientId,
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
