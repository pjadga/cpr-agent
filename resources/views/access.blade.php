@extends('layouts.cyberpunk')

@section('content')
    <div class="settings-page max-w-2xl mx-auto p-8">
        <div class="flex justify-between flex-wrap items-center mb-8 border-b-2 border-cpr-border pb-4">
            <h2 class="text-4xl font-bold text-cpr-red uppercase tracking-tighter">
                Access
            </h2>
            @unless($firstTime)
                <a href="{{ route('settings') }}"
                   class="cyber-button bg-cpr-black-elevated border-cpr-border text-xs px-4 py-2 uppercase w-32 text-center ml-auto">
                    Return
                </a>
            @endunless
        </div>

        @if(session('success'))
            <div class="bg-cpr-teal-dark/50 border border-cpr-teal text-cpr-teal p-4 mb-8 uppercase text-xs tracking-widest">
                {{ session('success') }}
            </div>
        @endif

        @if($firstTime)
            <p class="text-sm text-cpr-text-muted mb-8">
                Welcome. You signed in with the username you were given. Keep it or choose your own, and add a
                PIN or a pattern if you'd like a quicker way in. Any one of them unlocks your account, so keep
                them to yourself. Everything here is optional except a username.
            </p>
        @endif

        <form method="post" action="{{ route('access.update') }}" class="space-y-10"
              x-data="{ pinOn: false, patternOn: false }">
            @csrf
            @method('put')

            <div class="settings-section">
                <h3 class="settings-heading">Username</h3>
                <x-text-input id="username" name="username" type="text" class="cyber-input-field mt-1 w-full"
                              :value="old('username', $user->username)" required maxlength="50"
                              autocomplete="off" autocapitalize="none" spellcheck="false"/>
                <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase" :messages="$errors->get('username')"/>
                @if($user->given_username && $user->given_username !== $user->username)
                    <p class="text-[10px] text-cpr-text-dim mt-1 uppercase">Your gamemaster gave you: {{ $user->given_username }}</p>
                @endif
            </div>

            <div class="settings-section">
                <h3 class="settings-heading">PIN</h3>
                <p class="text-sm text-cpr-text-muted mb-2">
                    {{ $user->pin_hash ? 'You have a PIN.' : 'No PIN yet.' }} 4 to 12 digits.
                </p>
                <div class="flex flex-wrap gap-2">
                    <x-text-input name="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="12"
                                  class="cyber-input-field flex-1" placeholder="{{ $user->pin_hash ? 'New PIN' : 'PIN' }}"
                                  autocomplete="new-password"/>
                    <x-text-input name="pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="12"
                                  class="cyber-input-field flex-1" placeholder="Repeat it" autocomplete="new-password"/>
                </div>
                <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase" :messages="$errors->get('pin')"/>
                @if($user->pin_hash)
                    <label class="flex items-center mt-2 text-xs uppercase text-cpr-text-dim">
                        <input type="checkbox" name="clear_pin" value="1" class="rounded me-2"> Remove my PIN
                    </label>
                @endif
            </div>

            <div class="settings-section">
                <h3 class="settings-heading">Pattern</h3>
                <p class="text-sm text-cpr-text-muted mb-4">
                    {{ $user->pattern_hash ? 'You have a pattern. Draw a new one to replace it.' : 'No pattern yet. Draw one to add it.' }}
                </p>
                <x-pattern-lock name="pattern"/>
                <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase text-center" :messages="$errors->get('pattern')"/>
                @if($user->pattern_hash)
                    <label class="flex items-center justify-center mt-2 text-xs uppercase text-cpr-text-dim">
                        <input type="checkbox" name="clear_pattern" value="1" class="rounded me-2"> Remove my pattern
                    </label>
                @endif
            </div>

            <div class="settings-section">
                <h3 class="settings-heading">Recovery Email</h3>
                <p class="text-sm text-cpr-text-muted mb-2">
                    Recommended. If you forget your username, PIN and pattern, the Agent can send a one-time
                    sign-in link here. It's used for nothing else, and nobody else can see it.
                </p>
                <x-text-input name="email" type="email" class="cyber-input-field mt-1 w-full"
                              :value="old('email', $user->email)" autocomplete="email" placeholder="Optional"/>
                <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase" :messages="$errors->get('email')"/>
            </div>

            <button type="submit"
                    class="cyber-button bg-cpr-teal-dark border-cpr-teal-dark w-full uppercase font-bold py-3 hover:bg-cpr-teal text-black">
                {{ $firstTime ? 'Save and Continue' : 'Save' }}
            </button>
        </form>
    </div>
@endsection
