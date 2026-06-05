<?php

namespace App\Http\Controllers\Api;

use App\Events\FoundryResponse;
use App\Http\Controllers\Controller;
use App\Jobs\SyncCharacterDataToDb;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Log;

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
            'agentLink' => 'nullable|string',
            'isGM' => 'nullable|boolean',
            'playerName' => 'nullable|string',
            'flavor' => 'nullable|string',
            'formula' => 'nullable|string',
            'target' => 'nullable|string',
            'tokenId' => 'nullable|string',
            'channelId' => 'nullable|string',
            'channel_id' => 'nullable|string',
            'message' => 'nullable|string',
            'rollMode' => 'nullable|string',
            'pools' => 'nullable|array',
        ]);

        $agentLink = $validated['agentLink'] ?? $validated['agent_link'] ?? null;
        $type = $validated['type'] ?? null;
        $actorId = $validated['actorId'] ?? ($validated['actor']['_id'] ?? null);

        // Try to identify the user first for all request types
        $user = null;
        if ($agentLink) {
            $normalizedLink = rtrim($agentLink, '/');
            $user = User::where('agent_link', $normalizedLink)
                ->orWhere('agent_link', $normalizedLink.'/')
                ->first();
        }

        if (! $user && $actorId) {
            $cleanActorId = str_replace('Actor.', '', $actorId);
            $user = User::where('default_actor_id', $actorId)
                ->orWhere('default_actor_id', $cleanActorId)
                ->first();
        }

        // Fallback to the user associated with the API token
        if (! $user) {
            $user = $request->user();
        }

        // If it's a link request, we might be creating/updating a user
        if ($type === 'LINK_PLAYER_CHARACTER' || $type === 'GM_LINK_PLAYER_CHARACTER') {
            $targetUser = null;
            $userIdentifier = $validated['agentLink'] ?? $validated['agent_link'] ?? $validated['foundryUserId'] ?? null;

            if (! $user) {
                $user = $request->user();
            }

            // If it's a GM-initiated link, find the target user by agentLink or foundryUserId
            if ($type === 'GM_LINK_PLAYER_CHARACTER' && ! empty($userIdentifier)) {
                $targetUser = User::where('agent_link', $userIdentifier)->first();
                if ($targetUser) {
                    $user = $targetUser;
                } else {
                    Log::info("GM tried to sync character for Foundry User but no Laravel user found with identifier [{$userIdentifier}].");

                    return response()->json(['success' => false, 'message' => 'Target user not found']);
                }
            }

            $user->default_actor_id = is_array($validated['character']) ? ($validated['character']['id'] ?? null) : $validated['character'];

            if (isset($validated['isGM'])) {
                $user->is_gm = $validated['isGM'];
            }

            $user->save();

            Log::info("Character link updated for User [{$user->id}] (Actor: {$user->default_actor_id}) via {$type}");

            return response()->json([
                'success' => true,
                'agent_link' => $user->agent_link,
            ]);
        }

        // General update for is_gm if present and user identified
        if ($user && isset($validated['isGM'])) {
            if ($user->is_gm !== (bool) $validated['isGM']) {
                $user->is_gm = $validated['isGM'];
                $user->save();
                Log::info("User [{$user->id}] is_gm status updated to [{$user->is_gm}] via {$type}");
            }
        }

        // Persist actor data to the user's mirror if it's a full update or a delta
        if (in_array($type, ['ACTOR_DATA', 'SHEET_DELTA']) && $user) {
            // If update is not for the user's assigned character, only GMs should save it
            if ($user->default_actor_id && $actorId && $user->default_actor_id !== $actorId && str_replace('Actor.', '', $user->default_actor_id) !== str_replace('Actor.', '', $actorId)) {
                $authUser = $request->user();
                if ($authUser && $authUser->is_gm) {
                    // Logic to handle GM-driven updates if needed
                }
            }

            $current = Cache::get("user_char_{$user->id}");
            // Fetch from DB if no Foundry call
            if (! $current) {
                $current = $user->character_data ?? [];
            }

            // TODO This should throw error
            if (! is_array($current)) {
                $current = [];
            }

            if ($type === 'ACTOR_DATA' && ! empty($validated['actor'])) {
                $current = $validated['actor'];
            } elseif ($type === 'SHEET_DELTA' && ! empty($validated['delta'])) {
                foreach ($validated['delta'] as $path => $value) {
                    $this->arraySetDot($current, $path, $value);
                }
            }

            // Update Redis Cache (High Speed)
            Cache::put("user_char_{$user->id}", $current, 3600);

            // Queue the DB sync (Async Persistence)
            SyncCharacterDataToDb::dispatch($user->id, $current);
        }

        // Broadcast to the Web Client
        $broadcastPayload = $validated;
        if ($agentLink) {
            $broadcastPayload['agentLink'] = $agentLink;
        }

        // Add channel ID if we have a user
        $incomingChannelId = $validated['channel_id'] ?? $validated['channelId'] ?? null;
        if ($user && $user->channel_id) {
            $broadcastPayload['channel_id'] = $user->channel_id;
        } elseif ($incomingChannelId) {
            $broadcastPayload['channel_id'] = $incomingChannelId;
        }

        FoundryResponse::dispatch($broadcastPayload);

        // Put in cache for synchronous polling
        if (! empty($validated['requestId'])) {
            Cache::put('foundry_response_'.$validated['requestId'], $request->all(), 10);
        }

        $duration = round((microtime(true) - $start) * 1000, 2);
        Log::info("Foundry Callback [{$type}] processed in {$duration}ms", [
            'requestId' => $validated['requestId'] ?? 'N/A',
        ]);

        return response()->json(['status' => 'acknowledged', 'processing_ms' => $duration]);
    }

    /**
     * Helper to set an array value by dot-notated path
     */
    public function arraySetDot(array &$array, string $path, $value): void
    {
        // Special case for items: if path is "items.+ID", we append the item to the items array
        if (str_starts_with($path, 'items.+')) {
            if (! isset($array['items']) || ! is_array($array['items'])) {
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

            if (! $found) {
                $array['items'][] = $value;
            }

            return;
        }

        $keys = explode('.', $path);

        // Special handling for items.ID.field pattern
        if (count($keys) >= 3 && $keys[0] === 'items') {
            $itemId = $keys[1];
            $found = false;

            foreach ($array['items'] as $index => &$item) {
                if (isset($item['_id']) && $item['_id'] === $itemId) {
                    $found = true;
                    // Build the remaining path after items.ID
                    $remainingPath = implode('.', array_slice($keys, 2));

                    // Navigate to the nested property
                    $ref = &$item;
                    $remainingKeys = array_slice($keys, 2);
                    foreach ($remainingKeys as $i => $key) {
                        if ($i === count($remainingKeys) - 1) {
                            if ($value === null) {
                                unset($ref[$key]);
                            } else {
                                $ref[$key] = $value;
                            }
                        } else {
                            if (! isset($ref[$key]) || ! is_array($ref[$key])) {
                                $ref[$key] = [];
                            }
                            $ref = &$ref[$key];
                        }
                    }
                    break;
                }
            }

            if (! $found) {
                // Item not found, can't update
                return;
            }

            return;
        }

        // Handle items.ID deletion or replacement
        if (count($keys) === 2 && $keys[0] === 'items') {
            $itemId = $keys[1];

            if ($value === null) {
                // Delete item by ID
                foreach ($array['items'] as $index => $item) {
                    if (isset($item['_id']) && $item['_id'] === $itemId) {
                        unset($array['items'][$index]);
                        $array['items'] = array_values($array['items']); // Re-index

                        return;
                    }
                }
            } else {
                // Replace entire item
                $found = false;
                foreach ($array['items'] as $index => $item) {
                    if (isset($item['_id']) && $item['_id'] === $itemId) {
                        $array['items'][$index] = $value;
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $array['items'][] = $value;
                }
            }

            return;
        }

        // Standard dot notation handling for non-items paths
        $ref = &$array;
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                if ($value === null) {
                    unset($ref[$key]);
                } else {
                    $ref[$key] = $value;
                }
            } else {
                if (! isset($ref[$key]) || ! is_array($ref[$key])) {
                    $ref[$key] = [];
                }
                $ref = &$ref[$key];
            }
        }
    }
}
