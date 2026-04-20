<?php

namespace App\Http\Controllers\Data;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class GetActors extends FormRequest
{
    use HasConfiguration;

    public function getActors(Request $request)
    {
        $user = auth()->user();
        $actorId = $user->getActorId();

        // Dispatch background request to Foundry to refresh the list
        $requestId = $this->createRequestId();

        // We always use GET_ACTORS to get the full available list for the user
        $type = 'GET_ACTORS';
        $payload = [
            'type' => $type,
            'requestId' => $requestId,
            'actorId' => $actorId,
        ];

        CyberpunkAgentChannel::dispatch($payload);

        // Return immediately (Asynchronous)
        // The frontend will receive the update via Echo/Pusher/Reverb
        return response()->json([
            'requestId' => $requestId,
            'status' => 'pending',
            'message' => 'Refreshing actor list from Foundry...',
        ], 202);
    }
}
