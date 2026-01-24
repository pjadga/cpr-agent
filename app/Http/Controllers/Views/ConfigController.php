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

        $config = $request->user()->config ?: new Config(['user_id' => $request->user()->id]);
        $config->fill($validated);
        $config->save();

        return redirect()->route('settings')->with('success', 'Configuration updated successfully.');
    }

    public function getBaseUrl(Request $request)
    {
        return $request->user()->config->base_url ?? null;
    }

    public function getFoundryUrl(Request $request)
    {
        return $request->user()->config->foundry_url ?? null;
    }
}
