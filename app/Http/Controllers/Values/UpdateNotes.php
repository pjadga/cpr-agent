<?php

namespace App\Http\Controllers\Values;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateNotes extends FormRequest
{
    use HasConfiguration;

    public function updateNotes(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'notes' => 'required|string',
        ]);

        $user = auth()->user();
        $actorId = $user->getActorId();
        $requestId = $this->createRequestId();

        $payload = [
            'type' => 'UPDATE_STAT',
            'actorId' => $actorId,
            'label' => 'information.notes',
            'operation' => 'set',
            'value' => $validatedData['notes'],
            'requestId' => $requestId,
        ];

        CyberpunkAgentChannel::dispatch($payload);

        return response()->json([
            'success' => true,
            'message' => 'Notes successfully submitted!',
            'data' => [
                'requestId' => $requestId,
            ],
        ], 201);
    }
}
