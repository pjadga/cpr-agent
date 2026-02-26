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
            'targetTokenId' => 'required|string|max:255',
            'actorId' => 'nullable|string|max:255',
            'tokenId' => 'nullable|string|max:255',
        ]);

        $targetTokenId = $validatedData["targetTokenId"];
        $user = auth()->user();
        $actorId = $validatedData['actorId'] ?? $user->getActorId();
        $tokenId = $validatedData['tokenId'] ?? $user->getTokenId();
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'TARGET_TOKEN',
            'targetTokenId' => $targetTokenId,
            'actorId' => $actorId,
            'tokenId' => $tokenId,
            'requestId' => $requestId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => [
                'requestId' => $requestId,
            ],
        ], 201);
    }
}
