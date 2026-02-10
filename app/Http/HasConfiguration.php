<?php

namespace App\Http;

use App\Models\Config;
use FoundryClient\FoundryClient;

trait HasConfiguration
{
    public function getApiKey(): ?string
    {
        $config = auth()->user()->config;
        return $config?->api_key;
    }

    public function getRelayUrl(): ?string
    {
        $config = auth()->user()->config;
        return $config->base_url ?? config('services.foundry.base_url');
    }

    /**
     * @throws \Exception
     */
    public function getFoundryClient(): FoundryClient
    {
        $apiKey = $this->getApiKey();
        $baseUrl = $this->getRelayUrl();

        if (!$apiKey || !$baseUrl) {
            throw new \Exception("Missing Foundry API Key or Base URL configuration.");
        }

        return new FoundryClient($apiKey, $baseUrl);
    }
}
