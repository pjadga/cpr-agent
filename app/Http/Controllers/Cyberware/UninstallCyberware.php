<?php

namespace App\Http\Controllers\Cyberware;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UninstallCyberware extends FormRequest
{
    use HasConfiguration;

    public function uninstallCyberware(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'itemId' => 'required|string|max:255',
            'foundationalId' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        $actorId = str_replace('Actor.', '', $user->getActorId());
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'UNINSTALL_CYBERWARE',
            'actorId' => $actorId,
            'itemId' => $validatedData['itemId'],
            'foundationalId' => $validatedData['foundationalId'] ?? null,
            'requestId' => $requestId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cyberware uninstall request sent.',
            'data' => [
                'requestId' => $requestId,
            ],
        ], 201);
    }
}
