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

        return redirect()->back()->with('success', 'Configuration updated successfully.');
    }
}
