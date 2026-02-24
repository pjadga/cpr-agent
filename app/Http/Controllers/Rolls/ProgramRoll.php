<?php

namespace App\Http\Controllers\Rolls;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramRoll extends FormRequest
{
    use HasConfiguration;

    public function programRoll(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'programName' => 'string|max:255',
            'programId' => 'string|max:255',
            'type' => 'string|max:255',
            'executionType' => 'string|max:255',
            'cyberdeckId' => 'string|max:255'
        ]);

        $programName = lcfirst($validatedData['programName'])  ?? null;
        $programId = $validatedData['programId']  ?? null;
        $cyberdeckId = $validatedData['cyberdeckId']  ?? null;
        $executionType = $validatedData['executionType']  ?? null;
        $type = $validatedData['type']  ?? null;

        $user = auth()->user();
        $actorId = $user->getActorId();
        $rollType = ($executionType ? 'cyberdeckProgram' : 'interfaceAbility');

        $requestId = uniqid();

        CyberpunkAgentChannel::dispatch([
            'type' => 'PROGRAM_ROLL',
            'actorId' => $actorId,
            'rollType' => $rollType,
            'cyberdeckId' => $cyberdeckId,
            'programName' => $programName,
            'programId' => $programId,
            'executionType' => $executionType,
            'requestId' => $requestId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => [
                'requestId' => $requestId
            ]
        ], 201);
    }
}
