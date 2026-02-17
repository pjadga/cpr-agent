<?php

namespace App\Http\Controllers\Rolls;

use App\Http\HasConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DiceRoller extends Controller
{
    use HasConfiguration;

    public function makeRoll(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'formula' => 'required|string',
            'flavor' => 'nullable|string',
            'createChatMessage' => 'nullable|boolean',
        ]);

        $user = auth()->user();

        if (!$user || !$user->getClientId()) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or Client ID missing.',
            ], 401);
        }

        $requestId = uniqid();

        $payload = [
            'type' => 'DICE_ROLL',
            'formula' => $validatedData['formula'],
            'flavor' => $validatedData['flavor'] ?? 'Manual Roll',
            'createChatMessage' => $validatedData['createChatMessage'] ?? true,
            'speaker' => 'Actor.' . $user->getActorId(),
            'requestId' => $requestId,
        ];

        \App\Events\CyberpunkAgentChannel::dispatch($payload);

        return response()->json([
            'success' => true,
            'data' => [
                'requestId' => $requestId
            ],
        ]);
    }

    public function getRolls(): JsonResponse
    {

        $user = auth()->user();

        if (!$user || !$user->getClientId()) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or Client ID missing.',
            ], 401);
        }

        return response()->json([
            'success' => true
        ]);
    }
}
