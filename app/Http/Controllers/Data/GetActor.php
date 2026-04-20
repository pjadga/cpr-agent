<?php

namespace App\Http\Controllers\Data;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class GetActor extends FormRequest
{
    use HasConfiguration;

    public function getActor(Request $request)
    {
        $user = auth()->user();
        // 1. Generate a unique ID for this specific transaction
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'GET_ACTOR',
            'requestId' => $requestId,
            'actorId' => str_replace('Actor.', '', $request->get('actorId')),
        ]);

        // 2. Return immediately (Asynchronous)
        // The frontend will receive the update via Echo/Pusher
        return response()->json([
            'requestId' => $requestId,
            'status' => 'pending',
            'message' => 'Request dispatched to Foundry VTT.',
        ], 202);
    }
}
