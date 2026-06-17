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
     * Sync user profile (actor selection + channel_id from session).
     */
    public function storeSync(Request $request)
    {
        $validated = $request->validate([
            'default_actor_id' => 'nullable|string',
            'default_token_id' => 'nullable|string',
            'channel_id' => 'nullable|string',
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
