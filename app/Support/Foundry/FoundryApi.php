<?php

namespace App\Support\Foundry;

use GuzzleHttp\Client;

class FoundryApi
{
    public function __construct(protected Client $client) {}

    public function getClientId(): ?string
    {
        $response = $this->client->post('cpra-entry', [
            'json' => [
                'action' => 'getClientId',
            ]
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['clientId'] ?? null;
    }
}
