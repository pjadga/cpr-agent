<?php

namespace App\Http\Controllers\Views;

use App\Models\Config;
use Illuminate\Http\Request;

class ConfigController
{
    public function index()
    {
        $config = Config::first() ?? new Config();
        return view('config', compact('config'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'api_key' => 'nullable|string',
            'base_url' => 'nullable|string',
        ]);

        $config = Config::first() ?: new Config();
        $config->fill($validated);
        $config->save();

        if ($request->filled('api_key') && $request->filled('base_url')) {
            return redirect()->route('dashboard')->with('success', 'Configuration updated successfully.');
        }

        return redirect()->back()->with('success', 'Configuration updated successfully.');
    }

    public function getBaseUrl(Request $request)
    {
        return Config::first()->base_url ?? null;
    }
}
