<?php

namespace App\Http\Controllers\Data;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class GetNetPool extends FormRequest
{
    use HasConfiguration;

    public function getNetPool(Request $request)
    {
        $requestId = $this->createRequestId();

        CyberpunkAgentChannel::dispatch([
            'type' => 'GET_NET_POOL',
            'requestId' => $requestId,
        ]);

        return response()->json([
            'requestId' => $requestId,
            'status' => 'pending',
            'message' => 'Net Pool request dispatched.',
        ], 202);
    }
}
