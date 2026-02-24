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
        $start = microtime(true);

        // 1. Validate the incoming data
        $validated = $request->validate([
            'requestId' => 'nullable|string',
            'type' => 'required|string',
            'action' => 'nullable|string',
            'actorId' => 'nullable|string',
            'success' => 'nullable|boolean',
            'result' => 'nullable', // For complex data like Roll Modifiers
            'actors' => 'nullable|array', // For GET_ACTORS
            'actor' => 'nullable|array',  // For GET_ACTOR
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

        $type = $validated['type'] ?? null;

        // 2. PERSISTENCE: If this is full actor data or a delta, save to the user's mirror
        if (in_array($type, ['ACTOR_DATA', 'SHEET_DELTA'])) {
            $user = $request->user();
            if ($user) {
                $current = $user->character_data ?? [];
                if (!is_array($current)) $current = [];

                if ($type === 'ACTOR_DATA' && !empty($validated['actor'])) {
                    $current = $validated['actor'];
                } elseif ($type === 'SHEET_DELTA' && !empty($validated['delta'])) {
                    // Apply dot-path updates
                    foreach ($validated['delta'] as $path => $value) {
                        $this->arraySetDot($current, $path, $value);
                    }
                }

                $user->character_data = $current;
                $user->save();
            }
        }

        // 3. BROADCAST to the Web Client
        \App\Events\FoundryResponse::dispatch($validated);

        // 4. Legacy Cache support (Phase 1)
        if (!empty($validated['requestId'])) {
            \Illuminate\Support\Facades\Cache::put('foundry_response_' . $validated['requestId'], $request->all(), 10);
        }

        $duration = round((microtime(true) - $start) * 1000, 2);
        \Log::info("Foundry Callback [{$type}] processed in {$duration}ms", [
            'requestId' => $validated['requestId'] ?? 'N/A'
        ]);

        return response()->json(['status' => 'acknowledged', 'processing_ms' => $duration]);
    }

    /**
     * Helper to set an array value by dot-notated path
     */
    protected function arraySetDot(array &$array, string $path, $value): void
    {
        // Special case for items: if path is "items.+ID", we append the item to the items array
        if (str_starts_with($path, 'items.+')) {
            if (!isset($array['items']) || !is_array($array['items'])) {
                $array['items'] = [];
            }

            // Avoid duplicates by ID
            $id = substr($path, 7);
            $found = false;
            foreach ($array['items'] as $index => $item) {
                if (isset($item['_id']) && $item['_id'] === $id) {
                    $array['items'][$index] = $value;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $array['items'][] = $value;
            }
            return;
        }

        $keys = explode('.', $path);
        $ref =& $array;
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                if ($value === null) {
                    // Deletion: items.ID
                    if ($i === 1 && $keys[0] === 'items') {
                        foreach ($ref as $index => $item) {
                            if (isset($item['_id']) && $item['_id'] === $key) {
                                unset($ref[$index]);
                                $ref = array_values($ref); // Re-index array
                                return;
                            }
                        }
                    }
                    unset($ref[$key]);
                } else {
                    // Addition/Replacement: items.ID
                    if ($i === 1 && $keys[0] === 'items') {
                        $found = false;
                        foreach ($ref as $index => $item) {
                            if (isset($item['_id']) && $item['_id'] === $key) {
                                $ref[$index] = $value;
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            $ref[] = $value;
                        }
                        return;
                    }
                    $ref[$key] = $value;
                }
            } else {
                if (!isset($ref[$key]) || !is_array($ref[$key])) {
                    $ref[$key] = [];
                }

                // If we are updating an item by ID: items.ID.field
                if ($key === 'items' && $i === 0 && count($keys) > 1) {
                    $itemId = $keys[$i+1];
                    $found = false;
                    foreach ($ref[$key] as $index => &$item) {
                        if (isset($item['_id']) && $item['_id'] === $itemId) {
                            $ref =& $item;
                            $found = true;
                            // Skip the ID key in the next iteration
                            $i++;
                            break;
                        }
                    }
                    if (!$found) {
                        // If item not found, we can't update it, so we stop
                        return;
                    }
                    continue;
                }

                $ref =& $ref[$key];
            }
        }
    }
}
