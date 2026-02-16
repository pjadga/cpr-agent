<?php

namespace App\Http\Controllers\Api;

use App\Events\FoundryResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FoundryController extends Controller
{
    /**
     * Handle the callback from Foundry.
     */
    public function handle(Request $request)
    {
        // 1. Validate the incoming data
        // We expect at least a 'type' (e.g., ACTORS_DATA, CONFIRMATION)
        $validated = $request->validate([
            'type' => 'required|string',
            'action' => 'nullable|string',
            'actorId' => 'nullable|string',
            'success' => 'nullable|boolean',
            'result' => 'nullable|array', // For complex data like Roll Modifiers
            'actors' => 'nullable|array', // For GET_ACTORS
            'tokens' => 'nullable|array', // For VISIBLE_TOKENS
        ]);

        // 2. Log it (Optional, good for debugging)
        // \Log::info('Foundry Callback:', $validated);

        // 3. Broadcast to the Web Client
        // This pushes the data via Reverb to the browser
        \App\Events\FoundryResponse::dispatch($validated);

        return response()->json(['status' => 'acknowledged']);
    }
}
