<?php

namespace App\Http\Controllers;

use App\Events\CyberpunkAgentChannel;
use App\Http\HasConfiguration;
use App\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProfileSetupController extends Controller
{
    use HasConfiguration;

    public function show()
    {
        $user = Auth::user();
        $config = $user->config ?? new Config();

        $clientIds = [];
        $actors = [];

        if ($config->base_url || config('services.foundry.base_url')) {
            $requestId = (string) Str::uuid();

            CyberpunkAgentChannel::dispatch([
                'type' => 'GET_ACTORS',
                'requestId' => $requestId,
            ]);

            // 2. Wait for the response (The Polling Loop)
            $startTime = microtime(true);
            $timeout = 3.0; // Seconds to wait
            $foundryData = null;

            while ((microtime(true) - $startTime) < $timeout) {
                $key = 'foundry_response_' . $requestId;

                if (Cache::has($key)) {
                    $foundryData = Cache::pull($key);
                    break;
                }

                usleep(100000); // 100ms
            }

            return view('settings', [
                'clientIds' => $clientIds,
                'actors' => $foundryData['actors'] ?? [],
                'user' => Auth::user(),
                'config' => $config,
            ]);
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
            'client_id' => 'nullable|string',
            'default_actor_id' => 'required|string',
            'default_token_id' => 'required|string',
        ]);

        $user = Auth::user();
        $user->update($validated);

        return redirect()->route('settings')->with('success', 'Profile synchronized successfully!');
    }
}
