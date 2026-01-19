<?php

namespace App\Http\Controllers\Token;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TargetTokens extends FormRequest
{
    use HasConfiguration;

    public function targetTokens(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'token' => 'required|string|max:255'
        ]);

        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->getClientId();
        $token = $validatedData["token"];
        $js = "const enemyTokenId = '$token';
                const targetToken = canvas.tokens.get(enemyTokenId);
                if (targetToken) {
                    targetToken.setTarget(true, { releaseOthers: true, groupSelection: true });
                    return true;
                } else {
                    return false;
                }";
        $return = $client->utilities->executeJs($clientId, $js);

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return
        ], 201);
    }
}
