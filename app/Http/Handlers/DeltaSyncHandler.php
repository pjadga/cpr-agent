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
        if (! $current) {
            $user = \App\Models\User::find($userId);
            $current = $user?->character_data ?? [];
        }

        // Ensure we're working with an array
        if (! is_array($current)) {
            $current = [];
        }

        // Apply the incoming data/delta
        if ($type === 'ACTOR_DATA' && ! empty($payload['actor'])) {
            $current = $payload['actor'];
        } elseif ($type === 'SHEET_DELTA' && ! empty($payload['delta'])) {
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
