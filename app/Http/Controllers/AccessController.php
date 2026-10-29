<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AccessCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A user's own ways in: their username, PIN and pattern (any one signs them in) and an optional
 * recovery email. Shown on first sign-in so they can replace the username the gamemaster gave them.
 */
class AccessController extends Controller
{
    public function edit(Request $request): View
    {
        return view('access', [
            'user' => $request->user(),
            'firstTime' => $request->user()->access_reviewed_at === null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'not_regex:/^\s|\s$/'],
            'pin' => ['nullable', 'string', 'regex:'.AccessCode::PIN, 'confirmed'],
            'pattern' => ['nullable', 'string'],
            'clear_pin' => ['boolean'],
            'clear_pattern' => ['boolean'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ], [
            'username.not_regex' => 'A username can\'t start or end with a space.',
            'pin.regex' => 'A PIN is 4 to 12 digits.',
        ]);

        self::applyAccess($user, $validated);
        $user->email = $validated['email'] ?? null;
        $user->access_reviewed_at = now();
        $user->save();

        return redirect()->route('dashboard')->with('success', 'Your sign-in options are saved.');
    }

    /**
     * Sets the username, PIN and pattern from validated input, refusing ones another account uses
     * (each signs in on its own, so two accounts can't share one). Shared with the Group page.
     *
     * @throws ValidationException
     */
    public static function applyAccess(User $user, array $input): void
    {
        $errors = [];

        $username = trim($input['username']);
        if ($user->accessTaken('username', $username)) {
            $errors['username'] = 'That username is taken.';
        }

        $pin = $input['pin'] ?? null;
        if (filled($pin) && $user->accessTaken('pin', $pin)) {
            $errors['pin'] = 'Choose a different PIN.';
        }

        $pattern = $input['pattern'] ?? null;
        if (filled($pattern) && ! AccessCode::validPattern($pattern)) {
            $errors['pattern'] = 'A pattern joins at least 4 dots.';
        } elseif (filled($pattern) && $user->accessTaken('pattern', $pattern)) {
            $errors['pattern'] = 'Choose a different pattern.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $user->username = $username;
        if (filled($pin)) {
            $user->setPin($pin);
        } elseif (! empty($input['clear_pin'])) {
            $user->setPin(null);
        }
        if (filled($pattern)) {
            $user->setPattern($pattern);
        } elseif (! empty($input['clear_pattern'])) {
            $user->setPattern(null);
        }
    }
}
