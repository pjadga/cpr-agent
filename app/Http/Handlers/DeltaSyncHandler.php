<?php

namespace App\Http\Handlers;

use App\Jobs\SyncCharacterDataToDb;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Handles ACTOR_DATA and SHEET_DELTA events.
 * Applies deltas to cache and queues async DB persistence.
 */
class DeltaSyncHandler
{
    public function handle(int $userId, string $type, array $payload): void
    {
        if (! in_array($type, ['ACTOR_DATA', 'SHEET_DELTA'])) {
            return;
        }

        $cacheKey = "user_char_{$userId}";
        $current = Cache::get($cacheKey);

        // Fallback to DB if cache is empty
        $user = null;
        if (! $current) {
            $user = \App\Models\User::find($userId);
            $current = $user?->character_data ?? [];
        }

        // Ensure we're working with an array
        if (! is_array($current)) {
            $current = [];
        }

        $normalise = fn ($id) => preg_replace('/^Actor\./', '', (string) $id);
        $user = $user ?? \App\Models\User::find($userId);
        $assignedId = $normalise($user?->default_actor_id);

        // Apply the incoming data/delta
        if ($type === 'ACTOR_DATA' && ! empty($payload['actor'])) {
            // Never let another actor's sheet (a GM fetch, a stray GET_ACTOR) overwrite
            // the assigned character's cache: the sheet would then fail the id check
            // and the app would lose its actor id.
            $incomingId = $normalise($payload['actor']['_id'] ?? '');
            if ($assignedId !== '' && $incomingId !== '' && $incomingId !== $assignedId) {
                Log::debug("Ignoring ACTOR_DATA for [{$incomingId}], user [{$userId}] is assigned [{$assignedId}]");

                return;
            }
            $current = $payload['actor'];
        } elseif ($type === 'SHEET_DELTA' && ! empty($payload['delta'])) {
            // A delta patches an existing sheet. With no base it would create a
            // fragment without an _id, and a delta for another actor corrupts this one.
            $baseId = $normalise($current['_id'] ?? '');
            $deltaId = $normalise($payload['actorId'] ?? $payload['uuid'] ?? '');
            if (empty($current) || ($baseId !== '' && $deltaId !== '' && $deltaId !== $baseId)) {
                Log::debug("Skipping SHEET_DELTA for user [{$userId}]: base [{$baseId}], delta [{$deltaId}]");

                return;
            }
            $applier = new \App\Http\Handlers\DeltaApplier();
            $applier->apply($current, $payload['delta']);
        }

        // Update Redis cache (high speed)
        Cache::put($cacheKey, $current, 3600);

        // Queue async DB sync
        SyncCharacterDataToDb::dispatch($userId, $current);

        Log::debug("Delta synced for user [{$userId}] via {$type}");
    }
}
