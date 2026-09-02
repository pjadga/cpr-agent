<?php

namespace App\Http\Controllers\Cyberware;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrepareCyberwareInstall extends FormRequest
{
    use HasConfiguration;

    public function prepareCyberwareInstall(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'itemId' => 'required|string|max:255',
        ]);

        $user = auth()->user();
        $actorId = str_replace('Actor.', '', $user->getActorId());
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'PREPARE_CYBERWARE_INSTALL',
            'actorId' => $actorId,
            'itemId' => $validatedData['itemId'],
            'requestId' => $requestId,
        ]);

        // Options return asynchronously as CYBERWARE_INSTALL_OPTIONS over the channel.
        return response()->json([
            'success' => true,
            'message' => 'Cyberware install options requested.',
            'data' => [
                'requestId' => $requestId,
            ],
        ], 201);
    }
}
