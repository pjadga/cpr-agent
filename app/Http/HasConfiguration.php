<?php

namespace App\Http;

use App\Models\Config;
use FoundryClient\FoundryClient;

trait HasConfiguration
{
    public function getApiKey(): string
    {
        $config = auth()->user()->config;
        return $config->api_key;
    }

    public function getBaseUrl(): string
    {
        $config = auth()->user()->config;
        return $config->base_url ?? config('services.foundry.base_url');
    }

    /**
     * @throws \Exception
     */
    public function getFoundryClient(): FoundryClient
    {
        return new FoundryClient($this->getApiKey(), $this->getBaseUrl());
    }
}
