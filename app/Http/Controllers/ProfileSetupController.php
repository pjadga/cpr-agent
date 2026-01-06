<?php

namespace App\Http\Controllers;

use App\Http\HasConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileSetupController extends Controller
{
    use HasConfiguration;

    public function show()
    {
        // Generate some dummy Client IDs as requested
        $clientIds = [
            'foundry-xECODN3n8rO4E5Cj' => 'No Client Available'
        ];

        $clientId = $this->getFoundryClient()->api->getClientId();

        if ($clientId !== null) {
            $clientIds = [
                $clientId => "Foundry Client Available"
            ];
        }

        // In a real scenario, we would fetch actors based on the client_id
        // For now, let's provide some dummy actors or a text input

        $getActors = $this->getFoundryClient()->utilities->executeJs($clientId, "let tokens=canvas.tokens.placeables.filter(t=>t.actor&&t.actor.hasPlayerOwner).map(t=>({name:t.name,actorID:t.actor.id,tokenID:t.id}));return tokens");

        $actors = [];
        foreach ($getActors["result"] as $actor) {
            $actors[] = [
                "name" => $actor["name"],
                "actorId" => $actor["actorID"],
                "tokenId" => $actor["tokenID"]
            ];

        }

        return view('profile-setup', [
            'clientIds' => $clientIds,
            'actors' => $actors,
            'user' => Auth::user(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|string',
            'default_actor_id' => 'required|string',
            'default_token_id' => 'required|string',
        ]);

        $user = Auth::user();
        $user->update($validated);

        return redirect()->route('dashboard')->with('status', 'Profile synchronized successfully!');
    }
}
