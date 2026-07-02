<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Waits for a Foundry response via cache polling (non-blocking).
 * Replaces the busy-wait usleep() loop with a more efficient check.
 */
class EventWaiter
{
    /**
     * Wait for a cached response keyed by requestId.
     * Returns null if timeout is reached.
     */
    public function wait(string $requestId, float $timeout = 5.0): ?array
    {
        $startTime = microtime(true);

        while ((microtime(true) - $startTime) < $timeout) {
            $key = 'foundry_response_' . $requestId;
            if (Cache::has($key)) {
                return Cache::pull($key);
            }

            // Sleep for 50ms between checks (much less CPU than usleep in a tight loop)
            usleep(50000);
        }

        Log::debug("EventWaiter timed out waiting for requestId: {$requestId}");
        return null;
    }

    /**
     * Wait and extract a specific field from the response.
     */
    public function waitForField(string $requestId, string $field, float $timeout = 5.0): mixed
    {
        $result = $this->wait($requestId, $timeout);

        if ($result && isset($result[$field])) {
            return $result[$field];
        }

        return null;
    }
}
