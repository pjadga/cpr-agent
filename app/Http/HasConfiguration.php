<?php

namespace App\Http;

use Illuminate\Support\Str;

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
     * Uses the agent_link if available, as requested.
     */
    public function createRequestId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Get the user's agent link ID.
     */
    public function getAgentLink(): ?string
    {
        return auth()->user()?->agent_link;
    }
}
