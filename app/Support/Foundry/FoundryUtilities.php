<?php

namespace App\Support\Foundry;

use GuzzleHttp\Client;

class FoundryUtilities
{
    public function __construct(protected Client $client) {}

    public function executeJs(string $clientId, string $js)
    {
        $response = $this->client->post('cpra-entry', [
            'json' => [
                'action' => 'executeJs',
                'clientId' => $clientId,
                'code' => $js,
            ]
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
