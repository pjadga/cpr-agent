<?php

namespace App\Support\Foundry;

use GuzzleHttp\Client;

class FoundryService
{
    public FoundryUtilities $utilities;
    public FoundryApi $api;
    public FoundryRoll $roll;
    public FoundryEntity $entity;

    public function __construct(string $apiKey, string $baseUrl)
    {
        $client = new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30.0,
        ]);

        $this->utilities = new FoundryUtilities($client);
        $this->api = new FoundryApi($client);
        $this->roll = new FoundryRoll($client);
        $this->entity = new FoundryEntity($client);
    }
}
