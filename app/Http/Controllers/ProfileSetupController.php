<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileSetupController extends Controller
{
    public function show()
    {
        // Generate some dummy Client IDs as requested
        $dummyClientIds = [
            'foundry-xECODN3n8rO4E5Cj' => 'Night City Main',
            'foundry-aB1C2D3E4F5G6H7' => 'Afterlife Hub',
            'foundry-zY9X8W7V6U5T4S3' => 'Badlands Outpost',
        ];

        // In a real scenario, we would fetch actors based on the client_id
        // For now, let's provide some dummy actors or a text input
        $dummyActors = [
            'Actor.BehNGodGRTaG7ClI' => 'Johnny Silverhand',
            'Actor.vMtjHtgJj3VENELV' => 'V',
            'Actor.xXxShadowxXx' => 'Jackie Welles',
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

        return redirect()->route('dashboard')->with('status', 'Profile updated successfully!');
    }
}
