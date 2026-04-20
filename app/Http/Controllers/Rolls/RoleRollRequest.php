<?php

namespace App\Http\Controllers\Rolls;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleRollRequest extends FormRequest
{
    use HasConfiguration;

    public function rollRole(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'label' => 'required|string|max:255',
            'role' => 'required|string|max:255',
            'isAbility' => 'required|boolean',
            'rollMode' => 'nullable|string',
        ]);

        $label = ucfirst($validatedData['label']); // Will also be role, but could be abilities
        $role = ucfirst($validatedData['role']); // Top  level role name

        $user = auth()->user();
        $actorId = $user->getActorId();
        $actorId = str_replace('Actor.', '', $actorId);

        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'ROLE_ROLL',
            'actorId' => $actorId,
            'itemName' => ($validatedData['isAbility'] ? $role : $label),
            'label' => $label,
            'isAbility' => $validatedData['isAbility'],
            'rollMode' => $validatedData['rollMode'] ?? null,
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
