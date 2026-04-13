<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImageProxy extends Controller
{
    public function proxy(Request $request)
    {
        $url = $request->query('url');

        if (!$url) {
            return response()->json(['error' => 'URL is required'], 400);
        }

        $cacheKey = 'img_proxy_' . md5($url);

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
                'type' => $type
            ], 86400);

            return response($body, 200)
                ->header('Content-Type', $type)
                ->header('Cache-Control', 'public, max-age=86400');

        } catch (\Exception $e) {
            Log::error("Image proxy exception for URL: $url", [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
