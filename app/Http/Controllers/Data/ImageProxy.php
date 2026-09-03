<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageProxy extends Controller
{
    public function proxy(Request $request)
    {
        $url = $request->query('url');

        if (! $url) {
            return response()->json(['error' => 'URL is required'], 400);
        }

        if (! $this->targetsOwnFoundry($request, $url)) {
            return response()->json(['error' => 'URL is not on your configured Foundry host'], 403);
        }

        $cacheKey = 'img_proxy_'.md5($url);

        // Try to get the image from cache
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);

            return response($cached['body'], 200)
                ->header('Content-Type', $cached['type'])
                ->header('Cache-Control', 'public, max-age=86400');
        }

        try {
            $response = Http::withHeaders([
                'ngrok-skip-browser-warning' => 'true',
                'User-Agent' => 'CyberpunkApp-Proxy/1.0',
            ])->timeout(10)->get($url);

            if ($response->failed()) {
                Log::error("Image proxy failed for URL: $url", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json(['error' => 'Failed to fetch image'], $response->status());
            }

            $body = $response->body();
            $type = $response->header('Content-Type');

            // Cache the response for 24 hours
            Cache::put($cacheKey, [
                'body' => $body,
                'type' => $type,
            ], 86400);

            return response($body, 200)
                ->header('Content-Type', $type)
                ->header('Cache-Control', 'public, max-age=86400');

        } catch (Exception $e) {
            Log::error("Image proxy exception for URL: $url", [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * The proxy exists to fetch the caller's own Foundry over a scheme their
     * browser refuses, so that is the only thing it will fetch.
     *
     * Without this the endpoint takes an arbitrary URL and has the server
     * request it — loopback, RFC1918, cloud metadata, anything. It is behind
     * auth, but registration is open on the hosted instance. Pinning to the
     * user's own configured host closes that while keeping LAN self-hosting
     * working, which a blanket private-range block would not: a self-hosted
     * Foundry legitimately lives on 192.168.x.x.
     */
    private function targetsOwnFoundry(Request $request, string $url): bool
    {
        $target = parse_url($url);

        if (! $target || ! in_array($target['scheme'] ?? '', ['http', 'https'], true) || empty($target['host'])) {
            return false;
        }

        $foundryUrl = $request->user()?->config?->foundry_url;

        if (! $foundryUrl) {
            return false;
        }

        $allowed = parse_url($foundryUrl);

        if (! $allowed || empty($allowed['host'])) {
            return false;
        }

        // Host and port must match. Scheme deliberately need not: the whole
        // point is proxying the http original from an https page.
        return strcasecmp($target['host'], $allowed['host']) === 0
            && $this->port($target) === $this->port($allowed);
    }

    /**
     * Effective port for a parsed URL, defaulting from its scheme.
     */
    private function port(array $parts): int
    {
        return (int) ($parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80));
    }
}
