<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RyanChandler\LaravelCloudflareTurnstile\Rules\Turnstile;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:player,gm'],
        ];

        if (config('services.turnstile.enabled')) {
            $rules['cf-turnstile-response'] = ['required', new Turnstile];
        }

        $request->validate($rules);

        $demoData = null;
        if (File::exists(base_path('json.json'))) {
            $demoData = json_decode(File::get(base_path('json.json')), true);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'character_data' => $demoData,
            'default_actor_id' => $demoData['_id'] ?? null,
            'default_token_id' => $demoData['_id'] ?? null,
            'agent_link' => (string) Str::uuid(),
            // Declared role. Foundry's isGM sync verifies/corrects this later ,
            // declaration bootstraps the GM flow (session, setup code) so the
            // Bridge can be configured at all. Changeable in Settings.
            'is_gm' => $request->role === 'gm',
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
