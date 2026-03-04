<?php

namespace App\Http\Controllers;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use App\Models\Config;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProfileSetupController extends Controller
{
    use HasConfiguration;

    public function show()
    {
        $user = Auth::user();
        $config = $user->config ?? new Config();
        $actors = [];

        if ($config->foundry_url || config('services.foundry.base_url')) {
            $requestId = $this->createRequestId();

            // Fetch the list of actors from Foundry.
            // We always use GET_ACTORS to populate the dropdown.
            $type = 'GET_ACTORS';
            $payload = [
                'type' => $type,
                'requestId' => $requestId,
                'agent_link' => $user->agent_link,
                'isLinkRequest' => true,
                'isGM' => (bool)$user->is_gm,
            ];
            CyberpunkAgentChannel::dispatch($payload);

            // 2. Wait for the response (The Polling Loop)
            $startTime = microtime(true);
            $timeout = 5.0; // Increased timeout

            while ((microtime(true) - $startTime) < $timeout) {
                $key = 'foundry_response_' . $requestId;

                if (Cache::has($key)) {
                    $foundryData = Cache::pull($key);
                    if (!empty($foundryData['actors'])) {
                        $actors = $foundryData['actors'];
                    }
                    break;
                }

                usleep(20000); // 20ms
            }
        }

        // Fallback: Ensure the currently saved actor is in the list
        // This ensures the current selection is displayed even if the bridge is offline or slow.
        if ($user->default_actor_id) {
            $found = false;
            foreach ($actors as $actor) {
                // Check if the actor is already in the list (handle actorId or _id)
                $id = $actor['actorId'] ?? ($actor['_id'] ?? '');
                if ($id == $user->default_actor_id) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $actors[] = [
                    'name' => $user->character_data['name'] ?? 'Saved Character',
                    'actorId' => $user->default_actor_id,
                    'tokenId' => $user->default_token_id,
                ];
            }
        }

        // Enhancement for GM: If the bridge is offline or the list is still empty,
        // include all other synced characters that exist in the database.
        if ($user->is_gm) {
            $otherUsers = User::whereNotNull('default_actor_id')
                ->where('id', '!=', $user->id)
                ->get();

            foreach ($otherUsers as $otherUser) {
                $found = false;
                foreach ($actors as $actor) {
                    $id = $actor['actorId'] ?? ($actor['_id'] ?? '');
                    if ($id == $otherUser->default_actor_id) {
                        $found = true;
                        break;
                    }
                }

                if (!$found && !empty($otherUser->character_data)) {
                    $actors[] = [
                        'name' => ($otherUser->character_data['name'] ?? 'Saved Character') . ' (' . $otherUser->name . ')',
                        'actorId' => $otherUser->default_actor_id,
                        'tokenId' => $otherUser->default_token_id,
                    ];
                }
            }
        }

        return view('settings', [
            'actors' => $actors,
            'user' => $user,
            'config' => $config,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'default_actor_id' => 'required|string',
            'default_token_id' => 'nullable|string',
            'channel_id' => 'nullable|string',
        ]);

        $user = Auth::user();

        // If the user doesn't have an agent-link, create one now.
        if (empty($user->agent_link)) {
            $validated['agent_link'] = (string) Str::uuid();
        }

        if (empty($validated['channel_id'])) {
            unset($validated['channel_id']);
        }

        $user->update($validated);

        $this->refreshCharacterData($user, $user->default_actor_id);

        return redirect()->route('settings')->with('success', 'Profile synchronized and character data refreshed!');
    }

    public function refresh()
    {
        $user = Auth::user();
        $this->refreshCharacterData($user);

        return redirect()->route('settings')->with('success', 'Character data refreshed from Foundry!');
    }

    protected function refreshCharacterData($user, $actorId = null)
    {
        // Wipe and replace character_data from Foundry
        $requestId = $this->createRequestId();

        $payload = [
            'type' => 'GET_ACTOR',
            'requestId' => $requestId,
            'agent_link' => $user->agent_link,
        ];

        if ($actorId) {
            $payload['actorId'] = $actorId;
        }

        // We don't pass actorId if it's null, so the bridge will return the currently assigned character for the user
        CyberpunkAgentChannel::dispatch($payload);

        // Wait for the response to ensure character_data is populated
        $startTime = microtime(true);
        $timeout = 5.0; // Increased timeout
        while ((microtime(true) - $startTime) < $timeout) {
            $key = 'foundry_response_' . $requestId;
            if (Cache::has($key)) {
                $foundryData = Cache::pull($key);
                if (!empty($foundryData['actor'])) {
                    $actor = $foundryData['actor'];

                    // If the character has changed, update the ID as well
                    if (isset($actor['_id']) && $actor['_id'] !== $user->default_actor_id) {
                        $user->default_actor_id = $actor['_id'];
                    }

                    $user->character_data = $actor;
                    $user->save();
                }
                break;
            }
            usleep(50000);
        }
    }
}
