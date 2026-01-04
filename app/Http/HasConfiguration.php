<?php

namespace App\Http;

use App\Models\Config;
use FoundryClient\FoundryClient;

trait HasConfiguration
{
    public function getApiKey(): string
    {
        $config = Config::first();
        return $config->api_key ?? "test-api-key";
    }

    public function getBaseUrl(): string
    {
        $config = Config::first();
        return $config->base_url ?? "https://foundry-api.surrealdisco.co.uk";
    }

    /**
     * @throws \Exception
     */
    public function getFoundryClient(): FoundryClient
    {
        return new FoundryClient($this->getApiKey(), $this->getBaseUrl());
    }
}
