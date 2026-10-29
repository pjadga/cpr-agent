@extends('layouts.cyberpunk')

@section('content')
    <div class="settings-page max-w-4xl mx-auto p-8">
        <div class="flex justify-between flex-wrap items-center mb-8 border-b-2 border-cpr-border pb-4">
            <h2 class="text-4xl font-bold text-cpr-red uppercase tracking-tighter">
                Group
            </h2>
            <div class="flex justify-between gap-2 pt-2 ml-auto">
                <a href="{{ route('settings') }}"
                   class="cyber-button bg-cpr-black-elevated border-cpr-border text-xs px-4 py-2 uppercase w-32 text-center">
                    Settings
                </a>
                <a href="{{ route('dashboard') }}"
                   class="cyber-button bg-cpr-black-elevated border-cpr-border text-xs px-4 py-2 uppercase w-32 text-center">
                    Return
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-cpr-teal-dark/50 border border-cpr-teal text-cpr-teal p-4 mb-8 uppercase text-xs tracking-widest">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-cpr-red-dark/50 border border-cpr-red text-cpr-red p-4 mb-8 uppercase text-xs tracking-widest">
                {{ session('error') }}
            </div>
        @endif

        <div class="settings-section">
            <h3 class="settings-heading">Add a Player</h3>
            <p class="text-sm text-cpr-text-muted mb-4">
                Give each player a username; it signs them in on its own. On their first sign-in they can keep it
                or choose their own, and add a PIN, a pattern and a recovery email.
            </p>
            <form method="post" action="{{ route('group.store') }}" class="flex flex-wrap gap-2 items-start">
                @csrf
                <div class="flex-1">
                    <x-text-input name="username" type="text" class="cyber-input-field w-full" :value="old('username')"
                                  required maxlength="50" placeholder="Username" autocomplete="off" autocapitalize="none"/>
                    <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase" :messages="$errors->get('username')"/>
                </div>
                <button type="submit"
                        class="cyber-button bg-cpr-teal-dark border-cpr-teal-dark px-6 py-2 uppercase font-bold text-xs hover:bg-cpr-teal text-black">
                    Add
                </button>
            </form>
        </div>

        <div class="settings-section">
            <h3 class="settings-heading">Accounts</h3>
            <div class="space-y-4">
                @foreach($users as $member)
                    @php($bag = $errors->getBag("user{$member->id}"))
                    <div class="border border-cpr-border p-4" x-data="{ open: @js($bag->isNotEmpty()) }">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex-1">
                                <p class="text-base">
                                    {{ $member->username }}
                                    @if($member->is_gamemaster) <span class="text-[10px] uppercase text-cpr-yellow">Gamemaster</span> @endif
                                    @if($member->is(auth()->user())) <span class="text-[10px] uppercase text-cpr-text-dim">(you)</span> @endif
                                </p>
                                @if($member->given_username && $member->given_username !== $member->username)
                                    <p class="text-[10px] uppercase text-cpr-yellow">Changed by the player · given: {{ $member->given_username }}</p>
                                @endif
                                <p class="text-[10px] uppercase text-cpr-text-dim">
                                    PIN {{ $member->pin_hash ? 'set' : '—' }} ·
                                    Pattern {{ $member->pattern_hash ? 'set' : '—' }} ·
                                    Recovery email {{ $member->email ? 'set' : '—' }} ·
                                    {{ $member->access_reviewed_at ? 'Signed in' : 'Not signed in yet' }}
                                </p>
                            </div>
                            <button type="button" x-on:click="open = ! open"
                                    class="cyber-button bg-cpr-black-elevated border-cpr-border px-4 py-2 uppercase text-xs">
                                Change
                            </button>
                            @unless($member->is_gamemaster)
                                <form method="post" action="{{ route('group.destroy', $member) }}"
                                      onsubmit="return confirm(@js('Remove '.$member->username.'? Their account and character link are deleted.'))">
                                    @csrf
                                    @method('delete')
                                    <button type="submit"
                                            class="cyber-button bg-cpr-red-dark border-cpr-red-dark px-4 py-2 uppercase text-xs hover:bg-cpr-red">
                                        Remove
                                    </button>
                                </form>
                            @endunless
                        </div>

                        <form method="post" action="{{ route('group.update', $member) }}" class="mt-4 space-y-4"
                              x-show="open" x-cloak>
                            @csrf
                            @method('patch')
                            <div>
                                <x-input-label :value="__('Username')" class="text-xs uppercase text-cpr-teal"/>
                                <x-text-input name="username" type="text" class="cyber-input-field mt-1 w-full"
                                              :value="$member->username" required maxlength="50" autocomplete="off"/>
                                <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase" :messages="$bag->get('username')"/>
                            </div>
                            <div>
                                <x-input-label :value="__('New PIN')" class="text-xs uppercase text-cpr-teal"/>
                                <x-text-input name="pin" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="12"
                                              class="cyber-input-field mt-1 w-full" placeholder="Unchanged" autocomplete="off"/>
                                <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase" :messages="$bag->get('pin')"/>
                                @if($member->pin_hash)
                                    <label class="flex items-center mt-2 text-xs uppercase text-cpr-text-dim">
                                        <input type="checkbox" name="clear_pin" value="1" class="rounded me-2"> Remove their PIN
                                    </label>
                                @endif
                            </div>
                            <div>
                                <x-input-label :value="__('New Pattern')" class="text-xs uppercase text-cpr-teal"/>
                                <x-pattern-lock name="pattern"/>
                                <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase text-center" :messages="$bag->get('pattern')"/>
                                @if($member->pattern_hash)
                                    <label class="flex items-center justify-center mt-2 text-xs uppercase text-cpr-text-dim">
                                        <input type="checkbox" name="clear_pattern" value="1" class="rounded me-2"> Remove their pattern
                                    </label>
                                @endif
                            </div>
                            <button type="submit"
                                    class="cyber-button bg-cpr-teal-dark border-cpr-teal-dark w-full uppercase font-bold py-2 hover:bg-cpr-teal text-black">
                                Save
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
