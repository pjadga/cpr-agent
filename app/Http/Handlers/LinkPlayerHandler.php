<?php

namespace App\Http\Handlers;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Handles LINK_PLAYER_CHARACTER and GM_LINK_PLAYER_CHARACTER events.
 */
class LinkPlayerHandler
{
    public function handle(User $user, array $payload): array
    {
        $type = $payload['type'];
        $character = $payload['character'] ?? null;
        $actorId = is_array($character) ? ($character['id'] ?? null) : $character;

        // For GM-initiated links, find the target user by agent_link or foundryUserId
        if ($type === 'GM_LINK_PLAYER_CHARACTER') {
            $userIdentifier = $payload['agentLink'] ?? $payload['agent_link'] ?? $payload['foundryUserId'] ?? null;

            if ($userIdentifier) {
                $targetUser = User::where('agent_link', $userIdentifier)->first();
                if ($targetUser) {
                    Log::info("GM linking character for user [{$targetUser->id}]");
                    return $this->updateCharacter($targetUser, $actorId, $payload['isGM'] ?? false);
                }

                Log::warning("GM tried to sync character but no Laravel user found with identifier [{$userIdentifier}]");
                return ['success' => false, 'message' => 'Target user not found'];
            }
        }

        // Player-initiated link (or fallback)
        return $this->updateCharacter($user, $actorId, $payload['isGM'] ?? false);
    }

    private function updateCharacter(User $user, ?string $actorId, bool $isGm): array
    {
        $user->default_actor_id = $actorId;

        // Foundry's isGM verifies the declared role ,  but never auto-demote a
        // user who owns an active session (e.g. a GM testing from a player
        // login would otherwise lose access to their own session management).
        if ($isGm || ! $user->ownsActiveSession()) {
            $user->is_gm = $isGm;
        }

        $user->save();

        Log::info("Character link updated for User [{$user->id}] (Actor: {$actorId})");

        return [
            'success' => true,
            'agent_link' => $user->agent_link,
        ];
    }
}
