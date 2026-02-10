<?php

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

            return response($response->body(), 200)
                ->header('Content-Type', $response->header('Content-Type'))
                ->header('Cache-Control', 'public, max-age=86400');

        } catch (\Exception $e) {
            Log::error("Image proxy exception for URL: $url", [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
