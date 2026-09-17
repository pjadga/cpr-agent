<?php

namespace App\Http\Controllers;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use App\Models\Config;
use App\Models\Session;
use App\Models\User;
use App\Support\EventWaiter;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        // Why this whole block is optional and wrapped: the actor list is a
        // convenience, but this page is also the only place a user can fix a
        // broken setup. Letting a dead Reverb throw out of here 500'd the one
        // screen they needed, so the two failures that stop the list arriving
        // are caught and reported instead of raised.
        $connectionIssue = null;

        // Only ask Foundry for actors when this user has actually told us where
        // their Foundry is. The old condition also accepted a
        // services.foundry.base_url that defaulted to the retired pre-Bridge
        // relay and was set by no env file, so it was always true: a brand new
        // account with nothing configured still dispatched a message nobody
        // could answer and sat through the full five second wait before
        // rendering. That config key is gone.
        if (! $config->foundry_url) {
            $connectionIssue = 'foundry_not_configured';
        } else {
            $requestId = $this->createRequestId();
            $payload = [
                'type' => 'GET_ACTORS',
                'requestId' => $requestId,
                'agent_link' => $user->agent_link,
                'isLinkRequest' => true,
                'isGM' => (bool) $user->is_gm,
            ];

            try {
                CyberpunkAgentChannel::dispatch($payload);

                // Wait for response using EventWaiter (non-blocking)
                $foundryData = app(EventWaiter::class)->wait($requestId, 5.0);

                if ($foundryData && ! empty($foundryData['actors'])) {
                    $actors = $foundryData['actors'];
                } else {
                    // Reverb took the message but nothing answered: Foundry is
                    // closed, or the Bridge module is not connected to it.
                    $connectionIssue = 'foundry_no_response';
                }
            } catch (BroadcastException $e) {
                // Could not even publish the message: Reverb is not running.
                Log::warning('Settings page could not reach Reverb', ['message' => $e->getMessage()]);
                $connectionIssue = 'realtime_unavailable';
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
            'connectionIssue' => $connectionIssue,
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
     * Switch the user's declared role (GM <-> Player).
     *
     * Declared at registration, changeable here. Guard: a GM who still owns an
     * active session must terminate it before demoting — otherwise the session
     * would be orphaned with no one able to manage it.
     */
    public function updateRole(Request $request)
    {
        $validated = $request->validate([
            'role' => ['required', 'in:player,gm'],
        ]);

        $user = Auth::user();
        $wantsGm = $validated['role'] === 'gm';

        if (! $wantsGm && $user->is_gm && $user->ownsActiveSession()) {
            return redirect()->route('settings')->with(
                'error',
                'Terminate your active session before switching to Player.'
            );
        }

        $user->is_gm = $wantsGm;
        $user->save();

        return redirect()->route('settings')->with(
            'success',
            $wantsGm ? 'Role updated — you are now a GM.' : 'Role updated — you are now a Player.'
        );
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

        // The address players actually load, straight from APP_URL. This used
        // to be assembled from the *WebSocket* scheme and APP_DOMAIN, so a
        // plain-HTTP self-host that (correctly) left VITE_REVERB_SCHEME unset
        // got handed https://<domain> — a URL its Bridge could never reach, and
        // one that made every player's QR scan fail as "wrong server".
        $appUrl = rtrim((string) config('app.url'), '/');

        // GMs always have a session by the time they get here (auto-created on
        // connection save), but ensure it so the code never carries a personal
        // channel_id that matches no session — that used to 404 pairing.
        $session = Session::ensureActiveFor($user);

        $payload = [
            'v' => 1,
            'agentUrl' => $appUrl,
            'channelId' => $session->code,
            'apiKey' => $config->api_key,
            'reverbKey' => config('broadcasting.connections.reverb.key'),
            // Only sent when Reverb genuinely lives on another port. Omitted on
            // a single-origin install so the Bridge keeps its own default and
            // derives the port from the Agent URL.
            'reverbPort' => config('vite.reverb.port') ?: null,
        ];

        // Short one-time code for cross-device setup (e.g. Agent on a phone,
        // Foundry on a desktop): the Bridge's Quick Setup redeems it over HTTP
        // instead of the GM shuttling a long base64 blob between devices.
        $shortCode = self::generateShortSetupCode();
        Cache::put(
            'foundry-setup-code:'.$shortCode,
            $payload,
            now()->addSeconds(self::SETUP_CODE_TTL)
        );

        return response()->json([
            'code' => base64_encode(json_encode($payload)),
            'short_code' => $shortCode,
            'expires_in' => self::SETUP_CODE_TTL,
            'has_channel' => ! empty($payload['channelId']),
        ]);
    }

    /**
     * Lifetime of a short setup code, in seconds.
     */
    public const SETUP_CODE_TTL = 300;

    /**
     * Generate a short, typeable setup code (e.g. "KX7M-4PWD"). Alphabet skips
     * ambiguous characters (0/O, 1/I/L) since GMs read this off a phone screen.
     */
    public static function generateShortSetupCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $pick = fn (int $n) => implode('', array_map(
            fn () => $alphabet[random_int(0, strlen($alphabet) - 1)],
            range(1, $n)
        ));

        return $pick(4).'-'.$pick(4);
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

        // Channel ID is part of the connection group. Players get it auto-filled
        // by joining a session (or can paste it); for GMs it is derived from
        // their session below, so manual input is ignored.
        if (! $user->is_gm && $request->has('channel_id') && ! empty($validated['channel_id'])) {
            $user->channel_id = $validated['channel_id'];
            $user->save();
        }

        // GM Quick Start: saving connection settings guarantees a session, puts
        // the GM on its channel, and keeps the session's Foundry config current
        // so players auto-configure. No manual create/sync sequencing needed.
        if ($user->is_gm) {
            $user->setRelation('config', $config);
            Session::ensureActiveFor($user);
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
