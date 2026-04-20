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

class SettingsController extends Controller
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
            $timeout = 5.0;
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
        if ($user->default_actor_id) {
            $found = false;
            foreach ($actors as $actor) {
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

        $isFirstTime = (!$config->api_key || !$config->foundry_url) && !$config->has_read_onboarding;

        return view('settings', [
            'actors' => $actors,
            'user' => $user,
            'config' => $config,
            'isFirstTime' => $isFirstTime,
        ]);
    }

    public function storeSync(Request $request)
    {
        // Fixing the bug where actor ID was required to save channel_id
        $validated = $request->validate([
            'default_actor_id' => 'nullable|string',
            'default_token_id' => 'nullable|string',
            'channel_id' => 'nullable|string',
        ]);

        $user = Auth::user();

        if (empty($user->agent_link)) {
            $user->agent_link = (string) Str::uuid();
        }

        // Update fields if they are present in the request
        if ($request->has('default_actor_id')) {
            $user->default_actor_id = $validated['default_actor_id'];
        }
        if ($request->has('default_token_id')) {
            $user->default_token_id = $validated['default_token_id'];
        }
        if ($request->has('channel_id')) {
            $user->channel_id = $validated['channel_id'];
        }

        $user->save();

        if ($user->default_actor_id && $request->has('default_actor_id')) {
            $this->refreshCharacterData($user, $user->default_actor_id);
        }

        return redirect()->route('settings')->with('success', 'Profile synchronized and character data refreshed!');
    }

    public function storeConfig(Request $request)
    {
        $validated = $request->validate([
            'api_key' => 'nullable|string',
            'foundry_url' => 'nullable|string',
            'theme' => 'nullable|string|in:night,day,bartmoss,corpo,medtech,blind',
        ]);

        $user = Auth::user();

        if (isset($validated['foundry_url'])) {
            $validated['foundry_url'] = rtrim($validated['foundry_url'], '/');
        }

        $config = $user->config ?: new Config(['user_id' => $user->id]);

        if ($request->has('api_key')) {
            $config->api_key = $validated['api_key'];
        }
        if ($request->has('foundry_url')) {
            $config->foundry_url = $validated['foundry_url'];
        }
        if ($request->has('theme')) {
            $config->theme = $validated['theme'];
        }

        $config->save();

        return redirect()->route('settings')->with('success', 'Configuration updated successfully.');
    }

    public function refresh()
    {
        $user = Auth::user();
        if ($user->default_actor_id) {
            $this->refreshCharacterData($user);
            return redirect()->route('settings')->with('success', 'Character data refreshed from Foundry!');
        }
        return redirect()->route('settings')->with('error', 'No character selected to refresh.');
    }

    public function getFoundryUrl(Request $request)
    {
        return $request->user()->config->foundry_url ?? null;
    }

    public function markOnboardingAsRead()
    {
        $user = Auth::user();
        $config = $user->config ?: new Config(['user_id' => $user->id]);
        $config->has_read_onboarding = true;
        $config->save();

        return response()->json(['success' => true]);
    }

    protected function refreshCharacterData($user, $actorId = null)
    {
        $requestId = $this->createRequestId();
        $payload = [
            'type' => 'GET_ACTOR',
            'requestId' => $requestId,
            'agent_link' => $user->agent_link,
        ];
        if ($actorId) {
            $payload['actorId'] = $actorId;
        }

        CyberpunkAgentChannel::dispatch($payload);

        $startTime = microtime(true);
        $timeout = 5.0;
        while ((microtime(true) - $startTime) < $timeout) {
            $key = 'foundry_response_' . $requestId;
            if (Cache::has($key)) {
                $foundryData = Cache::pull($key);
                if (!empty($foundryData['actor'])) {
                    $actor = $foundryData['actor'];
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
