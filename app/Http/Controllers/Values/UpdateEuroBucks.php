<?php

namespace App\Http\Controllers\Values;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateEuroBucks extends FormRequest
{
    use HasConfiguration;

    public function updateEuroBucks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:add,subtract,set',
            'value' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        $user = auth()->user();
        $actorId = $user->getActorId();
        $requestId = $this->createRequestId();
        $userName = $user->name ?? 'user';
        $reason = trim(($validated['reason'] ?? '') . ' - ' . $userName, ' -');

        $payload = [
            'type' => 'UPDATE_EB',
            'actorId' => $actorId,
            'action' => $validated['action'],   // add | subtract | set
            'value' => (int)$validated['value'],
            'reason' => $reason,
            'requestId' => $requestId,
        ];

        CyberpunkAgentChannel::dispatch($payload);

        return response()->json([
            'success' => true,
            'message' => 'Eurobucks update submitted',
            'requestId' => $requestId,
        ], 201);
    }
}
