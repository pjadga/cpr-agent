<?php

namespace App\Http\Controllers;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use App\Models\Config;
use App\Models\Session;
use App\Models\User;
use App\Support\EventWaiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    use HasConfiguration;

    /**
     * Display the settings page with actor list and session info.
     */
    public function show()
    {
        $user = Auth::user();
        $config = $user->config ?? new Config;
        $actors = [];

        // Fetch actors from Foundry if URL is configured
        if ($config->foundry_url || config('services.foundry.base_url')) {
            $requestId = $this->createRequestId();
            $payload = [
                'type' => 'GET_ACTORS',
                'requestId' => $requestId,
                'agent_link' => $user->agent_link,
                'isLinkRequest' => true,
                'isGM' => (bool) $user->is_gm,
            ];

            CyberpunkAgentChannel::dispatch($payload);

            // Wait for response using EventWaiter (non-blocking)
            $foundryData = app(EventWaiter::class)->wait($requestId, 5.0);
            if ($foundryData && ! empty($foundryData['actors'])) {
                $actors = $foundryData['actors'];
            }
        }

        // Fallback: ensure currently saved actor is in the list
        if ($user->default_actor_id) {
            $found = false;
            foreach ($actors as $actor) {
                $id = $actor['actorId'] ?? ($actor['_id'] ?? '');
                if ($id == $user->default_actor_id) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $actors[] = [
                    'name' => $user->character_data['name'] ?? 'Saved Character',
                    'actorId' => $user->default_actor_id,
                    'tokenId' => $user->default_token_id,
                ];
            }
        }

        // Determine session context for onboarding
        $sessionInfo = $this->getSessionInfo($user);
        $isFirstTime = (! $config->api_key || ! $config->foundry_url) && ! $config->has_read_onboarding;

        return view('settings', [
            'actors' => $actors,
            'user' => $user,
            'config' => $config,
            'isFirstTime' => $isFirstTime,
            'sessionInfo' => $sessionInfo,
        ]);
    }

    /**
     * Get session context for the current user.
     */
    private function getSessionInfo(User $user): ?array
    {
        // Check if GM with active session
        $gmSession = Session::where('gm_user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($gmSession) {
            return [
                'type' => 'gm',
                'code' => $gmSession->code,
                'player_count' => $gmSession->player_count,
                'foundry_configured' => ! empty($gmSession->foundry_config),
            ];
        }

        // Check if player in a session
        if ($user->channel_id) {
            $playerSession = Session::where('code', $user->channel_id)->first();
            if ($playerSession) {
                return [
                    'type' => 'player',
                    'code' => $playerSession->code,
                    'foundry_configured' => ! empty($playerSession->foundry_config),
                ];
            }
        }

        return null;
    }

    /**
     * Emit a one-paste "Foundry Setup Code" for the GM.
     *
     * Bundles everything the Agent Bridge module needs to connect — Agent URL,
     * Channel ID, the GM's API key, and the Reverb app key — as a base64-encoded
     * JSON blob the GM pastes into the Bridge's Quick Setup menu. The Bridge
     * derives Reverb host/port/SSL from the Agent URL, so only the key is sent.
     *
     * The Channel ID is the user's own `channel_id` — the same value shown in the
     * Channel ID field and used by the live broadcast channel (cpr-agent.{channel_id}).
     * It must match what the browser subscribes to, or channel auth rejects it.
     *
     * GM-only: the blob carries the GM's secret API key.
     */
    public function foundrySetupCode(Request $request)
    {
        $user = Auth::user();

        if (! $user->is_gm) {
            return response()->json(['error' => 'Only GMs can generate a Foundry setup code.'], 403);
        }

        $config = $user->config;
        if (! $config || empty($config->api_key)) {
            return response()->json([
                'error' => 'no_api_key',
                'message' => 'Generate an API key in Core System Settings first.',
            ], 422);
        }

        $payload = [
            'v' => 1,
            'agentUrl' => rtrim(config('app.url'), '/'),
            'channelId' => $user->channel_id,
            'apiKey' => $config->api_key,
            'reverbKey' => config('broadcasting.connections.reverb.key'),
        ];

        return response()->json([
            'code' => base64_encode(json_encode($payload)),
            'has_channel' => ! empty($payload['channelId']),
        ]);
    }

    /**
     * Sync user profile (actor selection + channel_id from session).
     */
    public function storeSync(Request $request)
    {
        $validated = $request->validate([
            'default_actor_id' => 'nullable|string',
            'default_token_id' => 'nullable|string',
            'channel_id' => 'nullable|string',
            'theme' => 'nullable|string|in:night,day,bartmoss,corpo,medtech,blind',
        ]);

        $user = Auth::user();

        if (empty($user->agent_link)) {
            $user->agent_link = (string) Str::uuid();
        }

        // Update fields if present
        if ($request->has('default_actor_id')) {
            $user->default_actor_id = $validated['default_actor_id'];
        }
        if ($request->has('default_token_id')) {
            $user->default_token_id = $validated['default_token_id'];
        }

        // Channel ID can come from session auto-join or manual entry
        if ($request->has('channel_id') && ! empty($validated['channel_id'])) {
            $user->channel_id = $validated['channel_id'];
        }

        $user->save();

        // Interface theme lives with the personal "Your Agent" group.
        if ($request->has('theme') && ! empty($validated['theme'])) {
            $config = $user->config ?: new Config(['user_id' => $user->id]);
            $config->theme = $validated['theme'];
            $config->save();
        }

        // Refresh character data if actor was set
        if ($user->default_actor_id && $request->has('default_actor_id')) {
            $this->refreshCharacterData($user, $user->default_actor_id);
        }

        return redirect()->route('settings')->with('success', 'Profile synchronized and character data refreshed!');
    }

    /**
     * Store/update user configuration (API key, Foundry URL, theme).
     */
    public function storeConfig(Request $request)
    {
        $validated = $request->validate([
            'api_key' => 'nullable|string',
            'foundry_url' => 'nullable|string',
            'channel_id' => 'nullable|string',
            'theme' => 'nullable|string|in:night,day,bartmoss,corpo,medtech,blind',
        ]);

        $user = Auth::user();
        $config = $user->config ?: new Config(['user_id' => $user->id]);

        if (isset($validated['foundry_url'])) {
            $validated['foundry_url'] = rtrim($validated['foundry_url'], '/');
        }

        if ($request->has('api_key')) {
            $config->api_key = $validated['api_key'];
        }
        if (isset($validated['foundry_url'])) {
            $config->foundry_url = $validated['foundry_url'];
        }
        if (isset($validated['theme'])) {
            $config->theme = $validated['theme'];
        }

        $config->save();

        // Channel ID is part of the connection group (GMs set it manually;
        // players get it auto-filled by joining a session).
        if ($request->has('channel_id') && ! empty($validated['channel_id'])) {
            $user->channel_id = $validated['channel_id'];
            $user->save();
        }

        // Keep the GM's session in step with their latest Foundry settings, so
        // players auto-configure without a manual "sync" click.
        if ($user->is_gm) {
            $user->setRelation('config', $config);
            $session = Session::where('gm_user_id', $user->id)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();

            $session?->syncFoundryConfigFor($user);
        }

        return redirect()->route('settings')->with('success', 'Configuration updated successfully.');
    }

    /**
     * Refresh character data from Foundry.
     */
    public function refresh()
    {
        $user = Auth::user();
        if ($user->default_actor_id) {
            $this->refreshCharacterData($user, null, false);

            return redirect()->route('settings')->with('success', 'Character data refreshed from Foundry!');
        }

        return redirect()->route('settings')->with('error', 'No character selected to refresh.');
    }

    /**
     * Get the user's Foundry URL (for frontend image proxy).
     */
    public function getFoundryUrl(Request $request)
    {
        return $request->user()->config->foundry_url ?? null;
    }

    /**
     * Mark onboarding as read.
     */
    public function markOnboardingAsRead()
    {
        $user = Auth::user();
        $config = $user->config ?: new Config(['user_id' => $user->id]);
        $config->has_read_onboarding = true;
        $config->save();

        return response()->json(['success' => true]);
    }

    /**
     * Refresh character data by dispatching GET_ACTOR and waiting for response.
     */
    protected function refreshCharacterData(User $user, ?string $actorId = null, bool $saveToUser = true): void
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

        // Use EventWaiter instead of busy-wait loop
        $foundryData = app(EventWaiter::class)->wait($requestId, 5.0);

        if ($foundryData && ! empty($foundryData['actor']) && $saveToUser) {
            $actor = $foundryData['actor'];
            if (isset($actor['_id']) && $actor['_id'] !== $user->default_actor_id) {
                $user->default_actor_id = $actor['_id'];
            }
            $user->character_data = $actor;
            $user->save();
        }
    }
}
