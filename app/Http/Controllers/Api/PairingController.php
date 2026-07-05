<?php

namespace App\Http\Controllers\Api;

use App\Events\CyberpunkAgentChannel;
use App\Http\Controllers\Controller;
use App\Models\AgentPairing;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PairingController extends Controller
{
    /**
     * Default lifetime of a pairing nonce, in seconds.
     */
    private const DEFAULT_TTL = 300;

    /**
     * GM's Bridge registers a one-time pairing nonce bound to a Foundry user.
     * Called with the GM's Sanctum token. The GM's Bridge remains the nonce
     * authority and verifies the broadcast round-trip itself; this row gives
     * the claim a server-side source of truth and a replay guard.
     */
    public function register(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->is_gm) {
            return response()->json(['error' => 'Only GMs can register pairings.'], 403);
        }

        $validated = $request->validate([
            'nonce' => ['required', 'string', 'max:128'],
            'channel_id' => ['required', 'string', 'max:64'],
            'foundry_user_id' => ['required', 'string', 'max:64'],
            'foundry_user_name' => ['nullable', 'string', 'max:255'],
            'foundry_url' => ['nullable', 'url'],
            'expires_in' => ['nullable', 'integer', 'min:30', 'max:3600'],
        ]);

        // The GM may only register pairings for a session they own.
        $session = Session::where('code', $validated['channel_id'])
            ->where('gm_user_id', $user->id)
            ->first();

        if (! $session) {
            return response()->json(['error' => 'No session with that code is owned by you.'], 404);
        }

        $pairing = AgentPairing::updateOrCreate(
            ['nonce' => $validated['nonce']],
            [
                'channel_id' => $validated['channel_id'],
                'foundry_user_id' => $validated['foundry_user_id'],
                'foundry_user_name' => $validated['foundry_user_name'] ?? null,
                'foundry_url' => $validated['foundry_url'] ?? ($session->foundry_config['foundry_url'] ?? null),
                'claimed_by_user_id' => null,
                'agent_link' => null,
                'claimed_at' => null,
                'expires_at' => now()->addSeconds($validated['expires_in'] ?? self::DEFAULT_TTL),
            ]
        );

        return response()->json([
            'success' => true,
            'nonce' => $pairing->nonce,
            'expires_at' => $pairing->expires_at,
        ]);
    }

    /**
     * Player's Agent claims a scanned pairing. Binds the player's agent_link to
     * the Foundry user, configures their Foundry URL + channel, and broadcasts
     * PAIR_AGENT so the GM's Bridge can flag the Foundry user.
     */
    public function claim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nonce' => ['required', 'string', 'max:128'],
        ]);

        $user = Auth::user();

        $pairing = AgentPairing::where('nonce', $validated['nonce'])->first();

        if (! $pairing) {
            return response()->json([
                'error' => 'pairing_not_found',
                'message' => 'This QR code is not recognised. Ask your GM to generate a fresh one.',
            ], 404);
        }

        if ($pairing->isClaimed()) {
            // Idempotent: if this same user already claimed it, treat as success.
            if ($pairing->claimed_by_user_id === $user->id) {
                return $this->claimSuccess($pairing);
            }

            return response()->json([
                'error' => 'pairing_already_claimed',
                'message' => 'This QR code has already been used. Ask your GM for a new one.',
            ], 409);
        }

        if ($pairing->isExpired()) {
            return response()->json([
                'error' => 'pairing_expired',
                'message' => 'This QR code has expired. Ask your GM to generate a fresh one.',
            ], 410);
        }

        // Configure the player from the pairing.
        $previousChannel = $user->channel_id;

        if (empty($user->agent_link)) {
            $user->agent_link = (string) Str::uuid();
        }
        $user->channel_id = $pairing->channel_id;
        $user->save();

        $user->config()->updateOrCreate(
            ['user_id' => $user->id],
            ['foundry_url' => $pairing->foundry_url ? rtrim($pairing->foundry_url, '/') : null]
        );

        // Maintain session player_count when the player joins a new session.
        // A GM claiming their own pairing (self-link via QR) isn't a player.
        if ($previousChannel !== $pairing->channel_id) {
            if ($previousChannel) {
                Session::where('code', $previousChannel)->where('player_count', '>', 0)->decrement('player_count');
            }
            Session::where('code', $pairing->channel_id)
                ->where('gm_user_id', '!=', $user->id)
                ->increment('player_count');
        }

        $pairing->update([
            'claimed_by_user_id' => $user->id,
            'agent_link' => $user->agent_link,
            'claimed_at' => now(),
        ]);

        // Tell the GM's Bridge which agent_link belongs to which Foundry user.
        event(new CyberpunkAgentChannel([
            'type' => 'PAIR_AGENT',
            'foundryUserId' => $pairing->foundry_user_id,
            'agentLink' => $user->agent_link,
            'agent_link' => $user->agent_link,
            'nonce' => $pairing->nonce,
            'channel_id' => $pairing->channel_id,
        ]));

        return $this->claimSuccess($pairing);
    }

    /**
     * Poll the status of a pairing (used by either side to detect completion).
     */
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nonce' => ['required', 'string', 'max:128'],
        ]);

        $pairing = AgentPairing::where('nonce', $validated['nonce'])->first();

        if (! $pairing) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $status = $pairing->isClaimed() ? 'claimed' : ($pairing->isExpired() ? 'expired' : 'pending');

        return response()->json([
            'status' => $status,
            'foundry_user_name' => $pairing->foundry_user_name,
            'channel_id' => $pairing->channel_id,
        ]);
    }

    private function claimSuccess(AgentPairing $pairing): JsonResponse
    {
        return response()->json([
            'success' => true,
            'channel_id' => $pairing->channel_id,
            'foundry_url' => $pairing->foundry_url,
            'foundry_user_name' => $pairing->foundry_user_name,
            'agent_link' => $pairing->agent_link,
            'message' => 'Agent linked to Foundry.',
        ]);
    }
}
