<?php

namespace App\Http\Controllers\Token;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TargetTokens extends FormRequest
{
    use HasConfiguration;

    public function targetTokens(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'token' => 'required|string|max:255'
        ]);

        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $token = $validatedData["token"];
        CyberpunkAgentChannel::dispatch([
            'type' => 'TARGET_TOKEN',
            'tokenId' => $token,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => null
        ], 201);
    }
}
