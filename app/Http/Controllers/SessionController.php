<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    /**
     * Create a new session (GM only).
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        if (! $user->is_gm) {
            return response()->json(['error' => 'Only GMs can create sessions.'], 403);
        }

        // Channel unification, self-heal and Foundry-config sync all live in
        // ensureActiveFor — shared with the auto-create on connection save.
        $session = Session::ensureActiveFor($user);

        return response()->json([
            'success' => true,
            'session' => [
                'code' => $session->code,
                'player_count' => $session->player_count,
                'message' => $session->wasRecentlyCreated
                    ? "Session created! Share this code with your players: {$session->code}"
                    : "You already have an active session. Share code: {$session->code}",
            ],
        ]);
    }

    /**
     * Join a session via code (players).
     */
    public function join(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ]);

        $user = Auth::user();
        $session = Session::where('code', strtoupper($validated['code']))->first();

        if (! $session) {
            return response()->json([
                'error' => 'Session not found. Check the code and try again.',
            ], 404);
        }

        if ($session->isExpired()) {
            return response()->json([
                'error' => 'This session has expired. Ask your GM to create a new one.',
            ], 410);
        }

        // If user is already in this session, just return info
        if ($user->channel_id === $session->code) {
            return response()->json([
                'success' => true,
                'already_joined' => true,
                'channel_id' => $session->code,
                'foundry_config' => $session->foundry_config ?? [],
                'message' => "You're already in session {$session->code}.",
            ]);
        }

        // If user is in a different session, leave it first
        if ($user->channel_id) {
            Session::where('code', $user->channel_id)->decrement('player_count');
        }

        $result = $session->joinPlayer($user);

        return response()->json($result);
    }

    /**
     * Get session info (for dashboard display).
     */
    public function show(Request $request)
    {
        $user = Auth::user();

        // Check if user is a GM with an active session
        $gmSession = Session::where('gm_user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($gmSession) {
            return response()->json([
                'is_gm_session' => true,
                'session' => [
                    'code' => $gmSession->code,
                    'player_count' => $gmSession->player_count,
                    'foundry_configured' => ! empty($gmSession->foundry_config),
                    'expires_at' => $gmSession->expires_at,
                ],
            ]);
        }

        // Check if user is a player in any session
        $playerSession = Session::where('code', $user->channel_id)->first();

        return response()->json([
            'is_gm_session' => false,
            'session' => $playerSession ? [
                'code' => $playerSession->code,
                'player_count' => $playerSession->player_count,
                'foundry_configured' => ! empty($playerSession->foundry_config),
                'expires_at' => $playerSession->expires_at,
            ] : null,
        ]);
    }

    /**
     * Leave the current session.
     */
    public function leave(Request $request)
    {
        $user = Auth::user();

        if (! $user->channel_id) {
            return response()->json(['error' => 'You are not in any session.'], 400);
        }

        $session = Session::where('code', $user->channel_id)->first();

        if ($session && $session->player_count > 0) {
            $session->decrement('player_count');
        }

        $user->channel_id = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'You have left the session.',
        ]);
    }

    /**
     * GM deletes their session (ends it for all players).
     */
    public function destroy(Request $request)
    {
        $user = Auth::user();

        if (! $user->is_gm) {
            return response()->json(['error' => 'Only GMs can delete sessions.'], 403);
        }

        $session = Session::where('gm_user_id', $user->id)->first();

        if (! $session) {
            return response()->json(['error' => 'No active session found.'], 404);
        }

        $code = $session->code;
        $session->delete();

        return response()->json([
            'success' => true,
            'message' => "Session {$code} has been terminated.",
        ]);
    }
}
