<?php

namespace App\Http\Controllers\Values;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateStat extends FormRequest
{
    use HasConfiguration;

    public function updateStat(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'label' => 'required|string|max:255',
            // When operation is 'set', expect 'value'; when 'inc', expect 'delta'
            'operation' => 'nullable|in:set,inc',
            'value' => 'nullable|int|max:255',
            'delta' => 'nullable|int|max:255',
        ]);

        $operation = $validatedData['operation'] ?? 'set';

        if ($operation === 'set' && !isset($validatedData['value'])) {
            return response()->json([
                'success' => false,
                'message' => 'Value is required for set operation',
            ], 422);
        }
        if ($operation === 'inc' && !isset($validatedData['delta'])) {
            return response()->json([
                'success' => false,
                'message' => 'Delta is required for inc operation',
            ], 422);
        }

        // Map label to system path
        $label = strtolower($validatedData['label']);
        if ($label === 'hit points' || $label === 'hp') {
            $label = 'derivedStats.hp.value';
        } elseif ($label === 'humanity') {
            $label = 'derivedStats.humanity.value';
        } else {
            // luck, emp, and other stats
            $label = 'stats.' . $label . '.value';
        }

        $user = auth()->user();
        $actorId = $user->getActorId();

        $payload = [
            'type' => 'UPDATE_STAT',
            'actorId' => $actorId,
            'label' => $label,
            'operation' => $operation,
        ];

        if ($operation === 'set') {
            $payload['value'] = (int)$validatedData['value'];
        } else {
            $payload['delta'] = (int)$validatedData['delta'];
        }

        CyberpunkAgentChannel::dispatch($payload);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => null,
        ], 201);
    }
}
