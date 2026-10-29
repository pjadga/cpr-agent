<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Account recovery for a user who forgot their username, PIN and pattern: a one-time sign-in link
 * to the recovery email they added, which opens their Access page. Without one, the gamemaster can
 * tell them their username or set a new one.
 */
class RecoveryController extends Controller
{
    public function create(): View
    {
        return view('auth.recover');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $user = User::where('email', mb_strtolower($request->string('email')))->first();
        if ($user) {
            // The password broker's tokens: hashed, one-time, and expiring (auth.passwords.users.expire).
            $token = Password::broker()->createToken($user);
            $link = route('recover.show', ['token' => $token, 'email' => $user->email]);
            Mail::raw("Open this link to sign in to the Agent and choose new sign-in options:\n\n{$link}\n\n"
                ."It works once and expires in ".config('auth.passwords.users.expire').' minutes. '
                .'If you didn\'t ask for it, ignore this email.', function ($message) use ($user) {
                    $message->to($user->email)->subject('Sign in to the Agent');
                });
        }

        // The same answer either way, so the page doesn't reveal which emails have accounts.
        return back()->with('status', 'If that email is on an account, a sign-in link is on its way.');
    }

    public function show(Request $request, string $token): RedirectResponse
    {
        $user = User::where('email', mb_strtolower((string) $request->query('email')))->first();

        if (! $user || ! Password::broker()->tokenExists($user, $token)) {
            return redirect()->route('login')->withErrors(['value' => 'That sign-in link has expired or was already used.']);
        }

        Password::broker()->deleteToken($user);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('access.edit')->with('success', 'You\'re signed in. Choose new sign-in options.');
    }
}
