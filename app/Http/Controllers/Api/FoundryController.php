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
            'rawRoll' => 'nullable|array',
            'delta' => 'nullable|array', // For selective sheet updates (path => value)
            'tab' => 'nullable|string'   // Optional hint for which tab was affected
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
            // We store the whole request data because GetActor/GetActors expect nested data like 'actor' or 'actors'
            \Illuminate\Support\Facades\Cache::put('foundry_response_' . $validated['requestId'], $request->all(), 10);
        }

        // 3. If this is a sheet delta, persist it in the user's cached mirror before broadcasting
        if (($validated['type'] ?? null) === 'SHEET_DELTA' && !empty($validated['delta'])) {
            $user = $request->user();
            if ($user) {
                $current = $user->character_data ?? [];
                if (!is_array($current)) {
                    $current = [];
                }
                // Apply dot-path updates
                foreach ($validated['delta'] as $path => $value) {
                    $this->arraySetDot($current, $path, $value);
                }
                $user->character_data = $current;
                $user->save();
            }
        }

        // 4. Broadcast to the Web Client
        // This pushes the data via Reverb to the browser
        \Log::info('Foundry Callback Dispatching Event:', ['payload' => $validated]);
        \App\Events\FoundryResponse::dispatch($validated);

        return response()->json(['status' => 'acknowledged']);
    }

    /**
     * Helper to set an array value by dot-notated path
     */
    protected function arraySetDot(array &$array, string $path, $value): void
    {
        $keys = explode('.', $path);
        $ref =& $array;
        foreach ($keys as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref =& $ref[$key];
        }
        $ref = $value;
    }
}
