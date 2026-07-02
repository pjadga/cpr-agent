<?php

namespace App\Http\Handlers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Identifies which user a Foundry callback belongs to.
 */
class IdentifyUserHandler
{
    /**
     * Try to identify the user from the request payload.
     * Order: agent_link > actor_id (via default_actor_id) > auth token
     */
    public function handle(Request $request, array $validated): ?User
    {
        $agentLink = $validated['agentLink'] ?? $validated['agent_link'] ?? null;
        $actorId = $validated['actorId'] ?? ($validated['actor']['_id'] ?? null);

        // 1. Try agent_link first (most reliable)
        if ($agentLink) {
            $normalizedLink = rtrim($agentLink, '/');
            $user = User::where('agent_link', $normalizedLink)
                ->orWhere('agent_link', $normalizedLink.'/')
                ->first();

            if ($user) {
                Log::debug("Identified user [{$user->id}] via agent_link");

                return $user;
            }
        }

        // 2. Try actor_id (fallback for players who haven't linked yet)
        if ($actorId) {
            $cleanActorId = str_replace('Actor.', '', $actorId);
            $user = User::where('default_actor_id', $actorId)
                ->orWhere('default_actor_id', $cleanActorId)
                ->first();

            if ($user) {
                Log::debug("Identified user [{$user->id}] via actor_id {$actorId}");

                return $user;
            }
        }

        // 3. Fallback to auth token user
        $authUser = $request->user();
        if ($authUser) {
            Log::debug("Identified user [{$authUser->id}] via auth token (fallback)");

            return $authUser;
        }

        Log::warning('Could not identify user from request', ['type' => $validated['type'] ?? 'unknown']);

        return null;
    }
}
