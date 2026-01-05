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
        $dummyClientIds = [
            'foundry-xECODN3n8rO4E5Cj' => 'No Client Available'
        ];

        $clientId = $this->getFoundryClient()->api->getClientId();

        if ($clientId !== null) {
            $dummyClientIds = [
                $clientId => "Client Online"
            ];
        }

        // In a real scenario, we would fetch actors based on the client_id
        // For now, let's provide some dummy actors or a text input
        $dummyActors = [
            'Actor.UGkNHSnOykhJEjPm' => 'Johnny Silverhand',
            'Actor.BehNGodGRTaG7ClI' => 'V',
            'Actor.88CUpTpxicp5Lz2Z' => 'Jackie Welles',
        ];

        return view('profile-setup', [
            'clientIds' => $dummyClientIds,
            'actors' => $dummyActors,
            'user' => Auth::user(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|string',
            'default_actor_id' => 'required|string',
        ]);

        $user = Auth::user();
        $user->update($validated);

        return redirect()->route('dashboard')->with('status', 'Profile synchronized successfully!');
    }
}
