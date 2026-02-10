<?php

namespace App\Http\Controllers\Views;

use App\Models\Config;
use Illuminate\Http\Request;

class ConfigController
{
    public function index()
    {
        return redirect()->route('settings');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'api_key' => 'nullable|string',
            'base_url' => 'nullable|string',
            'foundry_url' => 'nullable|string',
            'theme' => 'nullable|string|in:night,day',
        ]);

        if (isset($validated['base_url'])) {
            $validated['base_url'] = rtrim($validated['base_url'], '/');
        }

        if (isset($validated['foundry_url'])) {
            $validated['foundry_url'] = rtrim($validated['foundry_url'], '/');
        }

        $config = $request->user()->config ?: new Config(['user_id' => $request->user()->id]);
        $config->fill($validated);
        $config->save();

        return redirect()->route('settings')->with('success', 'Configuration updated successfully.');
    }

    public function getRelayUrl(Request $request)
    {
        return $request->user()->config->base_url ?? config('services.foundry.base_url');
    }

    public function getFoundryUrl(Request $request)
    {
        return $request->user()->config->foundry_url ?? null;
    }
}
