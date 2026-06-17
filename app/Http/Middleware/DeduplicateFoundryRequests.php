<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevents duplicate processing of Foundry callback requests.
 * If the same requestId arrives within a short window, it's ignored.
 */
class DeduplicateFoundryRequests
{
    private const TTL = 30; // seconds to remember processed requestIds

    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->json()->all();
        $requestId = $payload['requestId'] ?? null;
        $type = $payload['type'] ?? null;

        // Only deduplicate events that can cause side effects
        $deduplicatableTypes = [
            'SHEET_DELTA', 'ACTOR_DATA', 'LINK_PLAYER_CHARACTER',
            'GM_LINK_PLAYER_CHARACTER', 'ROLL_RESULT', 'DICE_ROLL_EVENT'
        ];

        if ($requestId && in_array($type, $deduplicatableTypes)) {
            $cacheKey = 'foundry_dedup_' . md5($requestId);

            if (Cache::has($cacheKey)) {
                return response()->json([
                    'status' => 'duplicate_ignored',
                    'requestId' => $requestId,
                ], 200); // Still return 200 to avoid retry loops in Foundry
            }

            // Mark as processed
            Cache::put($cacheKey, true, self::TTL);
        }

        return $next($request);
    }
}
