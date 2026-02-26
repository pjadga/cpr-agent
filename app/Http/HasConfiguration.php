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

    /**
     * Create a unique request ID for a Foundry transaction.
     */
    public function createRequestId(): string
    {
        $user = auth()->user();
        $prefix = $user ? 'user-' . $user->id . '-' : 'anon-';
        return $prefix . \Illuminate\Support\Str::random(12);
    }
}
