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

        $client = $this->getFoundryClient();
        $user = auth()->user();

        if (!$user || !$user->getClientId()) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or Client ID missing.',
            ], 401);
        }

        $clientId = $user->getClientId();

        $payload = [
            'formula' => $validatedData['formula'],
            'flavor' => $validatedData['flavor'] ?? 'Manual Roll',
            'createChatMessage' => $validatedData['createChatMessage'] ?? true,
            'speaker' => 'Actor.' . $user->getActorId(),
        ];

        $result = $client->roll->makeRoll($clientId, $payload);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function getRolls(): JsonResponse
    {
        $client = $this->getFoundryClient();
        $user = auth()->user();

        if (!$user || !$user->getClientId()) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or Client ID missing.',
            ], 401);
        }

        $clientId = $user->getClientId();
        $rolls = $client->roll->getRolls($clientId, 10);

        \Log::info('DiceRoller::getRolls response', ['rolls' => $rolls]);

        return response()->json([
            'success' => true,
            'data' => $rolls,
        ]);
    }
}
