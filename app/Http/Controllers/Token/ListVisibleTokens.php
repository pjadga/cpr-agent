<?php

namespace App\Http\Controllers\Token;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ListVisibleTokens extends FormRequest
{
    use HasConfiguration;

    public function listVisibleTokens(): JsonResponse
    {
        $user = auth()->user();
        $tokenId = $user->getTokenId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'LIST_VISIBLE_TOKENS',
            'tokenId' => $tokenId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Visible tokens request sent to Foundry',
            'data' => null
        ], 201);
    }
}
