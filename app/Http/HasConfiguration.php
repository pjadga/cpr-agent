<?php

namespace App\Http;

trait HasConfiguration
{
    public function getApiKey(): ?string
    {
        $config = auth()->user()->config;
        return $config?->api_key;
    }

    public function getFoundryUrl(): ?string
    {
        $config = auth()->user()->config;
        return $config?->foundry_url;
    }
}
