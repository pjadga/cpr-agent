<?php

namespace App\Http\Controllers\Token;

use App\Http\HasConfiguration;
use FoundryClient\FoundryClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoveToken extends FormRequest
{
    use HasConfiguration;

    public function moveToken(Request $request): JsonResponse
    {
        // Validate incoming request
        $validatedData = $request->validate([
            'direction' => 'required|string|max:255'
        ]);

        //Need to get the actors either ID or Name -
        //The app needs a way of selecting an actor to follow - then we have access to it's ID.
        $direction = $validatedData['direction'];
        $client = $this->getFoundryClient();
        $user = auth()->user();
        $clientId = $user->client_id ?? "foundry-xECODN3n8rO4E5Cj";

        $return = "fuck";
        if ($direction == "up") {
            $return = $client->utilities->executeJs($clientId,"const token = canvas.tokens.controlled[0]; const direction = token.document.y - canvas.grid.size; await token.document.update({ y: direction });");
        } elseif ($direction == "down") {
            $return = $client->utilities->executeJs($clientId,"const token = canvas.tokens.controlled[0]; const direction = token.document.y + canvas.grid.size; await token.document.update({ y: direction });");
        } elseif ($direction == "left") {
            $return = $client->utilities->executeJs($clientId,"const token = canvas.tokens.controlled[0]; const direction = token.document.x - canvas.grid.size; await token.document.update({ x: direction });");
        } elseif ($direction == "right") {
            $return = $client->utilities->executeJs($clientId,"const token = canvas.tokens.controlled[0]; const direction = token.document.x + canvas.grid.size; await token.document.update({ x: direction });");
        }
        return response()->json([
            'success' => true,
            'message' => 'Data successfully submitted!',
            'data' => $return,
        ], 201);
    }
}
