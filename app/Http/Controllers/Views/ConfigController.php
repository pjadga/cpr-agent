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
        ]);

        $config = Config::first() ?: new Config();
        $config->fill($validated);
        $config->save();

        return redirect()->route('settings')->with('success', 'Configuration updated successfully.');
    }

    public function getBaseUrl(Request $request)
    {
        return Config::first()->base_url ?? null;
    }

    public function getFoundryUrl(Request $request)
    {
        return Config::first()->foundry_url ?? null;
    }
}
