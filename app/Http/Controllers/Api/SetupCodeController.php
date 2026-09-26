<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SetupCodeController extends Controller
{
    /**
     * Redeem a short setup code minted by SettingsController::foundrySetupCode.
     *
     * Public but throttled: the code is a one-time, short-TTL bearer for the
     * GM's Bridge configuration (including their API key). Cache::pull makes
     * redemption single-use, a second request gets a 404.
     */
    public function redeem(string $code): JsonResponse
    {
        $normalized = strtoupper(trim($code));

        $payload = Cache::pull('foundry-setup-code:'.$normalized);

        if (! $payload) {
            return response()->json([
                'error' => 'setup_code_invalid',
                'message' => 'This setup code is invalid, expired, or already used. Generate a fresh one in the Agent settings.',
            ], 404);
        }

        return response()->json($payload);
    }
}
