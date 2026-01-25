<?php

namespace App\Http;

use App\Support\Foundry\FoundryService;
use App\Models\Config;

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
        return $config->base_url;
    }

    /**
     * @throws \Exception
     */
    public function getFoundryClient(): FoundryService
    {
        return new FoundryService($this->getApiKey(), $this->getBaseUrl());
    }
}
