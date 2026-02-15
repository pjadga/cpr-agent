<?php

namespace App\Http\Controllers;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use App\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileSetupController extends Controller
{
    use HasConfiguration;

    public function show()
    {
        $user = Auth::user();
        $config = $user->config ?? new Config();

        $clientIds = [
            'foundry-xECODN3n8rO4E5Cj' => 'No Client Available'
        ];

        $actors = [];

        if ($config->api_key && ($config->base_url || config('services.foundry.base_url'))) {
            try {
                $client = $this->getFoundryClient();
                $clientId = $client->api->getClientId();

                if ($clientId !== null) {
                    $clientIds = [
                        $clientId => "Foundry Client Available"
                    ];

                    CyberpunkAgentChannel::dispatch([
                        'type' => 'GET_ACTORS',
                    ]);
                }
            } catch (\Exception $e) {
                // Handle case where API is down or config is wrong
            }
        }

        return view('settings', [
            'clientIds' => $clientIds,
            'actors' => $actors,
            'user' => Auth::user(),
            'config' => $config,
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

        return redirect()->route('settings')->with('success', 'Profile synchronized successfully!');
    }
}
