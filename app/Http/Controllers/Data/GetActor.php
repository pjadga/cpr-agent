<?php

namespace App\Http\Controllers\Data;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Str;

class GetActor extends FormRequest
{
    use HasConfiguration;

    public function getActor(Request $request)
    {
        // 1. Generate a unique ID for this specific transaction
        $requestId = (string) Str::uuid();

        CyberpunkAgentChannel::dispatch([
            'type' => 'GET_ACTOR',
            'requestId' => $requestId,
            'actorId' => str_replace("Actor.", "", $request->get('actorId'))
        ]);

        // 2. Return immediately (Asynchronous)
        // The frontend will receive the update via Echo/Pusher
        return response()->json([
            'requestId' => $requestId,
            'status' => 'pending',
            'message' => 'Request dispatched to Foundry VTT.'
        ], 202);
    }
}
