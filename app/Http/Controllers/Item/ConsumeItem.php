<?php

namespace App\Http\Controllers\Item;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ConsumeItem extends Controller
{
    use HasConfiguration;

    /**
     * Consume an item (e.g. drug).
     */
    public function consume(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'itemId' => 'required|string',
        ]);

        $user = auth()->user();
        $actorId = $user->getActorId();

        try {
            $itemId = $validatedData['itemId'];
            $requestId = $this->createRequestId();

            CyberpunkAgentChannel::dispatch([
                'type' => 'CONSUME_ITEM',
                'actorId' => $actorId,
                'itemId' => $itemId,
                'requestId' => $requestId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Item consumed successfully',
                'requestId' => $requestId,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
