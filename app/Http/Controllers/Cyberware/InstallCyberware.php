<?php

namespace App\Http\Controllers\Cyberware;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstallCyberware extends FormRequest
{
    use HasConfiguration;

    public function installCyberware(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'itemId' => 'required|string|max:255',
            'foundationalId' => 'nullable|string|max:255',
            'humanityLossType' => 'nullable|string|max:32', // roll | static | none
        ]);

        $user = auth()->user();
        $actorId = str_replace('Actor.', '', $user->getActorId());
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'INSTALL_CYBERWARE',
            'actorId' => $actorId,
            'itemId' => $validatedData['itemId'],
            'foundationalId' => $validatedData['foundationalId'] ?? null,
            'humanityLossType' => $validatedData['humanityLossType'] ?? 'none',
            'requestId' => $requestId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cyberware install request sent.',
            'data' => [
                'requestId' => $requestId,
            ],
        ], 201);
    }
}
