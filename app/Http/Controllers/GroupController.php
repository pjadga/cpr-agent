<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\User;
use App\Support\AccessCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The gamemaster's group list: adds players by username and changes anyone's username, PIN or
 * pattern. Shows when a player has replaced the username they were given, so the gamemaster can
 * remind a player who forgets theirs.
 */
class GroupController extends Controller
{
    public function index(): View
    {
        $this->gamemaster();

        return view('group', [
            'users' => User::orderByDesc('is_gamemaster')->orderBy('username')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->gamemaster();

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'not_regex:/^\s|\s$/'],
        ], ['username.not_regex' => 'A username can\'t start or end with a space.']);

        $username = trim($validated['username']);
        if (User::findByAccess('username', $username)) {
            return back()->withInput()->withErrors(['username' => 'That username is taken.']);
        }

        User::addPlayer($username);

        return redirect()->route('group.index')->with('success', "{$username} can now sign in with that username.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->gamemaster();

        $validated = $request->validateWithBag("user{$user->id}", [
            'username' => ['required', 'string', 'max:50', 'not_regex:/^\s|\s$/'],
            'pin' => ['nullable', 'string', 'regex:'.AccessCode::PIN],
            'pattern' => ['nullable', 'string'],
            'clear_pin' => ['boolean'],
            'clear_pattern' => ['boolean'],
        ], [
            'username.not_regex' => 'A username can\'t start or end with a space.',
            'pin.regex' => 'A PIN is 4 to 12 digits.',
        ]);

        AccessController::applyAccess($user, $validated);
        // A username the gamemaster sets is the one they gave.
        if ($user->isDirty('username')) {
            $user->given_username = $user->username;
        }
        $user->save();

        return redirect()->route('group.index')->with('success', "{$user->username} updated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->gamemaster();

        if ($user->is_gamemaster) {
            return redirect()->route('group.index')->with('error', 'The gamemaster account can\'t be removed.');
        }

        Session::where('code', $user->getRawOriginal('channel_id'))->where('player_count', '>', 0)->decrement('player_count');
        $user->tokens()->delete();
        $user->delete();

        return redirect()->route('group.index')->with('success', "{$user->username} removed.");
    }

    private function gamemaster(): void
    {
        abort_unless(Auth::user()?->is_gamemaster, 403);
    }
}
