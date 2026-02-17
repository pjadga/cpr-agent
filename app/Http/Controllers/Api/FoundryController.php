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
            'requestId' => 'nullable|string',
            'type' => 'required|string',
            'action' => 'nullable|string',
            'actorId' => 'nullable|string',
            'success' => 'nullable|boolean',
            'result' => 'nullable', // For complex data like Roll Modifiers
            'actors' => 'nullable|array', // For GET_ACTORS
            'tokens' => 'nullable|array', // For VISIBLE_TOKENS
            'data' => 'nullable|array',
            'total' => 'nullable',
            'isCriticalSuccess' => 'nullable|boolean',
            'isCriticalFailure' => 'nullable|boolean',
            'label' => 'nullable|string',
            'rawRoll' => 'nullable|array'
        ]);

        // 2. Log it (Optional, good for debugging)
        \Log::info('Foundry Callback Received:', [
            'type' => $request->input('type'),
            'requestId' => $request->input('requestId'),
            'has_total' => $request->has('total'),
            'has_rawRoll' => $request->has('rawRoll'),
            'all_data' => $request->all()
        ]);

        // If requestId is present, store in cache for polling
        if (!empty($validated['requestId'])) {
            \Illuminate\Support\Facades\Cache::put('foundry_response_' . $validated['requestId'], $validated, 10);
        }

        // 3. Broadcast to the Web Client
        // This pushes the data via Reverb to the browser
        \Log::info('Foundry Callback Dispatching Event:', ['payload' => $validated]);
        \App\Events\FoundryResponse::dispatch($validated);

        return response()->json(['status' => 'acknowledged']);
    }
}
