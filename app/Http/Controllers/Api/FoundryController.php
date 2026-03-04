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

        $validated = $request->validate([
            'requestId' => 'nullable|string',
            'type' => 'required|string',
            'action' => 'nullable|string',
            'actorId' => 'nullable|string',
            'success' => 'nullable|boolean',
            'result' => 'nullable',
            'actors' => 'nullable|array',
            'actor' => 'nullable',
            'tokens' => 'nullable|array',
            'data' => 'nullable|array',
            'total' => 'nullable',
            'isCriticalSuccess' => 'nullable|boolean',
            'isCriticalFailure' => 'nullable|boolean',
            'label' => 'nullable|string',
            'rawRoll' => 'nullable|array',
            'delta' => 'nullable|array',
            'tab' => 'nullable|string',
            'character' => 'nullable',
            'foundryUserId' => 'nullable|string',
            'agentLink' => 'nullable|string'
        ]);

        $agentLink = $validated['agentLink'] ?? null;

        if ($validated['type'] === 'LINK_PLAYER_CHARACTER' || $validated['type'] === 'GM_LINK_PLAYER_CHARACTER') {
            $user = null;
            $targetFoundryUserId = $validated['foundryUserId'] ?? null;

            // Try to find the user by agentLink first
            if ($agentLink) {
                $user = \App\Models\User::where('agent_link', $agentLink)->first();
            }

            if (!$user) {
                $user = $request->user();
            }

            // If it's a GM-initiated link, find the target user by agent_link (passed as foundryUserId)
            if ($validated['type'] === 'GM_LINK_PLAYER_CHARACTER' && !empty($targetFoundryUserId)) {
                $targetUser = \App\Models\User::where('agent_link', $targetFoundryUserId)->first();
                if ($targetUser) {
                    $user = $targetUser;
                } else {
                    \Log::info("GM tried to sync character for Foundry User but no Laravel user found with agent_link [{$targetFoundryUserId}].");
                    return response()->json(['success' => false, 'message' => 'Target user not found']);
                }
            }

            $user->default_actor_id = is_array($validated['character']) ? ($validated['character']['id'] ?? null) : $validated['character'];

            $user->save();

            \Log::info("Character link updated for User [{$user->id}] (Actor: {$user->default_actor_id}) via {$validated['type']}");

            return response()->json([
                'success' => true,
                'agent_link' => $user->agent_link
            ]);
        }

        $type = $validated['type'] ?? null;

        // Persist actor data to the user's mirror if it's a full update or a delta
        if (in_array($type, ['ACTOR_DATA', 'SHEET_DELTA'])) {
            $actorId = $validated['actorId'] ?? ($validated['actor']['_id'] ?? null);
            $user = null;

            if ($actorId) {
                $user = \App\Models\User::where('default_actor_id', $actorId)->first();
            }

            // Fallback to the current user (GM) if no assigned user found
            if (!$user) {
                $user = $request->user();
            }

            if ($user) {
                // If update is not for the user's assigned character, only GMs should save it
                if ($user->default_actor_id && $actorId && $user->default_actor_id !== $actorId) {
                    $user = $request->user();
                }

                if (!$user) return response()->json(['status' => 'ignored']);

                $current = $user->character_data ?? [];
                if (!is_array($current)) $current = [];

                if ($type === 'ACTOR_DATA' && !empty($validated['actor'])) {
                    $current = $validated['actor'];
                } elseif ($type === 'SHEET_DELTA' && !empty($validated['delta'])) {
                    foreach ($validated['delta'] as $path => $value) {
                        $this->arraySetDot($current, $path, $value);
                    }
                }

                $user->character_data = $current;
                $user->save();
            }
        }

        // Broadcast to the Web Client
        \App\Events\FoundryResponse::dispatch($validated);

        // Put in cache for synchronous polling
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
