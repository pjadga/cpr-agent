<?php

namespace App\Http\Controllers\Token;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use JShrink\Minifier;
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
        $js = "const enemyTokenId = '$token'; // This comes from your enemy list
                const targetToken = canvas.tokens.get(enemyTokenId);
                if (targetToken) {
                    targetToken.setTarget(true, { releaseOthers: true, groupSelection: true });
                    console.log('Successfully targeted: ' + targetToken.name);
                    return true;
                } else {
                console.error('Token ID not found on the current scene.');
                return false;
                }";
        $return = $client->utilities->executeJs($clientId,
            \JShrink\Minifier::minify($js)
        );

        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return
        ], 201);
    }
}
