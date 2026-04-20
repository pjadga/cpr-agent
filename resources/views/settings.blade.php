@extends('layouts.cyberpunk')

@section('content')
<form action="{{ route('token.create') }}" method="POST" id="token-create-form" class="hidden">
    @csrf
</form>
<div class="max-w-4xl mx-auto p-8" x-data="{ onboardingStep: @js($isFirstTime ? 1 : 0) }">
    <div class="flex justify-around flex-wrap items-center mb-8 border-b-2 border-cpr-border pb-4">
        <h2 class="text-4xl font-bold text-cpr-red uppercase tracking-tighter">
            Agent Settings
        </h2>
        <div class="flex gap-2 pt-2">
            <a href="{{ route('dashboard') }}" class="cyber-button bg-cpr-black-elevated border-cpr-border text-xs px-4 py-2 uppercase w-32 text-center">
                Return
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="cyber-button bg-cpr-red-dark border-cpr-red-dark text-xs px-4 py-2 uppercase hover:bg-cpr-red w-32">
                    Disconnect
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-cpr-teal-dark/50 border border-cpr-teal text-cpr-teal p-4 mb-8 uppercase text-xs tracking-widest">
            {{ session('success') }}
        </div>
    @endif



    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
        <!-- Global App Configuration -->
        <div class="space-y-6 relative">
            <h3 class="text-xl font-bold text-cpr-yellow uppercase tracking-widest border-b border-cpr-yellow/30 pb-2">
                Core System Settings
            </h3>
            <form action="{{ route('config.store') }}" method="POST" class="space-y-4">
                @csrf


                <div>
                    <div class="flex items-center gap-2">
                        <x-input-label readonly for="api_key" :value="__('API Key')" class="text-xs uppercase text-cpr-teal" />
                        @if(auth()->user()->is_gm)
                            <span class="ml-auto bg-cpr-red/20 border border-cpr-red text-cpr-red px-2 py-0.5 text-[10px] font-black uppercase tracking-widest animate-pulse">
                                GM Authorized
                            </span>
                        @endif
                        <div class="group relative flex items-center">
                            <i data-lucide="help-circle" class="w-3 h-3 text-cpr-text-dim hover:text-cpr-teal cursor-help transition-colors"></i>
                            <div class="absolute left-full ml-2 w-48 p-2 bg-cpr-black-deep border border-cpr-teal text-[10px] text-cpr-text-main uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                Must be the same key added to the Cyberpunk Agent Bridge module
                            </div>
                        </div>
                    </div>
                    <x-text-input type="text" name="api_key" id="api_key" value="{{ session('flash_token', old('api_key', $config->api_key)) }}" class="cyber-input-field mt-1 w-full" />
                </div>

                <div>
                    @if ($config->api_key)
                        <details class="group border border-cpr-teal/30 rounded-lg overflow-hidden bg-cpr-black-deep/40" {{ session('flash_token') ? 'open' : '' }}>
                            <summary class="flex items-center justify-between p-4 cursor-pointer hover:bg-cpr-teal/10 transition-colors list-none">
                                <h3 class="font-bold text-cpr-teal">API Key Generation</h3>
                                <div class="transition-transform duration-300 group-open:rotate-180">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-cpr-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </summary>
                            <div class="p-4 border-t border-cpr-teal/20">
                    @else
                        <h3 class="font-bold mb-4">API Key Generation</h3>
                    @endif

                    @if (session('flash_token'))
                        <div class="bg-green-800 p-4 mb-4 rounded">
                            <p class="font-mono text-sm break-all select-all">
                                {{ session('flash_token') }}
                            </p>
                            <p class="text-xs mt-2 text-green-300">
                                ⚠️ Copy this now. You won't see it again.
                            </p>
                        </div>
                    @endif

                    <button type="submit" form="token-create-form" class="cyber-button bg-cpr-teal-dark border-cpr-teal text-black w-full uppercase font-bold py-2">
                        Generate New Connection Key
                    </button>

                    @if ($config->api_key)
                            </div>
                        </details>
                    @endif
                </div>

                <div>
                    <div class="flex items-center gap-2">
                        <x-input-label for="foundry_url" :value="__('Foundry URL')" class="text-xs uppercase text-cpr-teal" />
                        <div class="group relative flex items-center">
                            <i data-lucide="help-circle" class="w-3 h-3 text-cpr-text-dim hover:text-cpr-teal cursor-help transition-colors"></i>
                            <div class="absolute left-full ml-2 w-48 p-2 bg-cpr-black-deep border border-cpr-teal text-[10px] text-cpr-text-main uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                The full URL of your Foundry VTT instance.
                            </div>
                        </div>
                    </div>
                    <x-text-input type="text" name="foundry_url" id="foundry_url" value="{{ old('foundry_url', $config->foundry_url) }}" class="cyber-input-field mt-1 w-full" />
                </div>

                <div>
                    <x-input-label for="theme" :value="__('Interface Theme')" class="text-xs uppercase text-cpr-teal" />
                    <select id="theme" name="theme" class="cyber-select-field mt-1 text-base">
                        <option value="night" {{ old('theme', $config->theme) == 'night' ? 'selected' : '' }} class="cyber-select-option">Night Mode</option>
                        <option value="day" {{ old('theme', $config->theme) == 'day' ? 'selected' : '' }} class="cyber-select-option">Day Mode</option>
                        <option value="bartmoss" {{ old('theme', $config->theme) == 'bartmoss' ? 'selected' : '' }} class="cyber-select-option">Rache Bartmoss</option>
                        <option value="corpo" {{ old('theme', $config->theme) == 'corpo' ? 'selected' : '' }} class="cyber-select-option">Corpo</option>
                        <option value="medtech" {{ old('theme', $config->theme) == 'medtech' ? 'selected' : '' }} class="cyber-select-option">Med Tech</option>
                        <option value="blind" {{ old('theme', $config->theme) == 'blind' ? 'selected' : '' }} class="cyber-select-option">Colour Blind Mode</option>
                    </select>
                </div>

                <button type="submit" class="cyber-button bg-cpr-teal-dark border-cpr-teal text-black w-full uppercase font-bold py-2">
                    Update Core Settings
                </button>
            </form>

            <!-- Step 1 Tooltip -->
            <div x-show="onboardingStep === 1"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute z-50 top-full mt-4 left-0 w-full bg-cpr-black border-2 border-cpr-teal shadow-[0_0_20px_rgba(0,255,159,0.3)] p-6"
                 style="display: none;">
                <div class="absolute -top-3 left-8 w-6 h-6 bg-cpr-black border-l-2 border-t-2 border-cpr-teal transform rotate-45"></div>

                <div class="flex items-center justify-between border-b border-cpr-teal/30 mb-4 pb-2">
                    <h2 class="text-xl font-bold text-cpr-teal uppercase tracking-tighter">
                        System Initialization <span class="text-xs font-mono ml-2 opacity-50">[STEP 01]</span>
                    </h2>
                    <div class="flex gap-1">
                        <div class="bg-cpr-teal w-3 h-1"></div>
                        <div class="bg-cpr-teal/20 w-3 h-1"></div>
                        <div class="bg-cpr-teal/20 w-3 h-1"></div>
                    </div>
                </div>

                <p class="text-cpr-text mb-4 leading-relaxed text-sm">
                    First, enter your Foundry URL and generate an API key. Paste this key into the Cyberpunk Red Agent Bridge in Foundry.
                </p>
                <div class="bg-cpr-teal/10 border-l-2 border-cpr-teal p-3 mb-6 italic text-xs text-cpr-text">
                    Welcome, Edgerunner. Before we hit the Net, we need to establish a stable uplink to your Foundry instance.
                </div>

                <div class="flex justify-end">
                    <button type="button" @click="onboardingStep = 2" class="cyber-button bg-cpr-teal-dark border-cpr-teal px-8 py-2 uppercase text-xs font-bold text-black">
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- User Profile Synchronization -->
        <div class="space-y-6">
            <div class="flex justify-between items-center border-b border-cpr-yellow/30 pb-2">
                <h3 class="text-xl font-bold text-cpr-yellow uppercase tracking-widest">
                    Agent Sync
                </h3>
                <div class="flex gap-2">
                    <a href="{{ route('settings.refresh') }}" class="text-cpr-yellow hover:text-cpr-yellow transition-colors" title="Refresh Sync">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </a>
                </div>
            </div>
            @if(!$config->foundry_url && !config('services.foundry.base_url'))
                <div class="bg-cpr-red-dark/30 border border-cpr-red-dark text-cpr-red p-4 text-xs italic">
                    SYSTEM OFFLINE: Configure Core System Settings to enable Agent synchronization.
                </div>
            @else
                <form method="POST" action="{{ route('profile-setup.store') }}" class="space-y-4">
                    @csrf
                    <!-- Default Actor ID -->
                    <div class="relative">
                        <div class="flex items-center gap-2">
                            <x-input-label for="actor_selection" :value="__('Primary Biometric Signature (Actor)')" class="text-xs uppercase text-cpr-muted" />
                            <div class="group relative flex items-center">
                                <i data-lucide="help-circle" class="w-3 h-3 text-cpr-text-dim hover:text-cpr-yellow cursor-help transition-colors"></i>
                                <div class="absolute left-full ml-2 w-48 p-2 bg-cpr-black-deep border border-cpr-yellow text-[10px] text-cpr-text-main uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                    Loads playable characters and player-owned characters from the current session.
                                </div>
                            </div>
                        </div>
                        <select id="actor_selection" class="cyber-select-field mt-1 text-base">
                            <option value="" disabled {{ !$user->default_actor_id ? 'selected' : '' }}>Select Biometric Data</option>
                            @foreach($actors as $actor)
                                <option value="{{ $actor['actorId'] }}|{{ $actor['tokenId'] }}" {{ ($user->default_actor_id == $actor["actorId"]) ? 'selected' : '' }} class="cyber-select-option">
                                    {{ $actor["name"] }} [{{ $actor["actorId"] }}]
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="default_actor_id" id="default_actor_id" value="{{ $user->default_actor_id }}">
                        <input type="hidden" name="default_token_id" id="default_token_id" value="{{ $user->default_token_id }}">
                        <x-input-error :messages="$errors->get('default_actor_id')" class="mt-1 text-cpr-red text-[10px] uppercase" />

                        <!-- Step 3 Tooltip -->
                        <div x-show="onboardingStep === 3"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute z-50 top-full mt-4 left-0 w-full bg-cpr-black border-2 border-cpr-teal shadow-[0_0_20px_rgba(0,255,159,0.3)] p-6"
                             style="display: none;">
                            <div class="absolute -top-3 left-8 w-6 h-6 bg-cpr-black border-l-2 border-t-2 border-cpr-teal transform rotate-45"></div>

                            <div class="flex items-center justify-between border-b border-cpr-teal/30 mb-4 pb-2">
                                <h2 class="text-xl font-bold text-cpr-teal uppercase tracking-tighter">
                                    System Initialization <span class="text-xs font-mono ml-2 opacity-50">[STEP 03]</span>
                                </h2>
                                <div class="flex gap-1">
                                    <div class="bg-cpr-teal w-3 h-1"></div>
                                    <div class="bg-cpr-teal w-3 h-1"></div>
                                    <div class="bg-cpr-teal w-3 h-1"></div>
                                </div>
                            </div>

                            <p class="text-cpr-text mb-4 leading-relaxed text-sm">
                                Assign a character in the Foundry Module, refresh the Agent, and select and save your character.
                            </p>
                            <div class="bg-cpr-teal/10 border-l-2 border-cpr-teal p-3 mb-6 italic text-xs text-cpr-text">
                                Finalizing biometric handshake.
                            </div>

                            <div class="flex justify-between items-center">
                                <button type="button" @click="onboardingStep = 2" class="cyber-button bg-cpr-black-elevated border-cpr-border px-6 py-2 uppercase text-xs text-cpr-text">
                                    Previous
                                </button>
                                <button type="button"
                                        @click="fetch('{{ route('settings.onboarding.read') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' } }).then(() => onboardingStep = 0)"
                                        class="cyber-button bg-cpr-teal-dark border-cpr-teal px-8 py-2 uppercase text-xs font-bold text-black">
                                    Begin Sync
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Channel ID -->
                    <div class="relative">
                        <div class="flex items-center gap-2">
                            <x-input-label for="channel_id" :value="__('Channel ID')" class="text-xs uppercase text-cpr-muted" />
                            <div class="group relative flex items-center">
                                <i data-lucide="help-circle" class="w-3 h-3 text-cpr-text-dim hover:text-cpr-yellow cursor-help transition-colors"></i>
                                <div class="absolute left-full ml-2 w-48 p-2 bg-cpr-black-deep border border-cpr-yellow text-[10px] text-cpr-text-main uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                    The unique channel ID for your game session. GMs generate this and share it with players.
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <x-text-input type="text" name="channel_id" id="channel_id" value="{{ old('channel_id', $user->channel_id) }}" class="cyber-input-field mt-1 w-full" />
                        </div>
                        <x-input-error :messages="$errors->get('channel_id')" class="mt-1 text-cpr-red text-[10px] uppercase" />

                        <!-- Step 2 Tooltip -->
                        <div x-show="onboardingStep === 2"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute z-50 top-full mt-4 left-0 w-full bg-cpr-black border-2 border-cpr-teal shadow-[0_0_20px_rgba(0,255,159,0.3)] p-6"
                             style="display: none;">
                            <div class="absolute -top-3 left-8 w-6 h-6 bg-cpr-black border-l-2 border-t-2 border-cpr-teal transform rotate-45"></div>

                            <div class="flex items-center justify-between border-b border-cpr-teal/30 mb-4 pb-2">
                                <h2 class="text-xl font-bold text-cpr-teal uppercase tracking-tighter">
                                    System Initialization <span class="text-xs font-mono ml-2 opacity-50">[STEP 02]</span>
                                </h2>
                                <div class="flex gap-1">
                                    <div class="bg-cpr-teal w-3 h-1"></div>
                                    <div class="bg-cpr-teal w-3 h-1"></div>
                                    <div class="bg-cpr-teal/20 w-3 h-1"></div>
                                </div>
                            </div>

                            <p class="text-cpr-text mb-4 leading-relaxed text-sm">
                                Make sure the Channel IDs match between the Module and Agent. Commit the Sync to save the channel ID.
                            </p>
                            <div class="bg-cpr-teal/10 border-l-2 border-cpr-teal p-3 mb-6 italic text-xs text-cpr-text">
                                Uplink established. Now, let's setup some Personal ICE.
                            </div>

                            <div class="flex justify-between items-center">
                                <button type="button" @click="onboardingStep = 1" class="cyber-button bg-cpr-black-elevated border-cpr-border px-6 py-2 uppercase text-xs text-cpr-text">
                                    Previous
                                </button>
                                <button type="button" @click="onboardingStep = 3" class="cyber-button bg-cpr-teal-dark border-cpr-teal px-8 py-2 uppercase text-xs font-bold text-black">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>


                    <button type="submit" class="cyber-button bg-cpr-teal-dark border-cpr-teal w-full uppercase font-bold py-2 text-black">
                        Commit Sync
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Account Management -->
    <div class="mt-12 pt-8 border-t-2 border-cpr-border">
        <h3 class="text-2xl font-bold text-cpr-yellow uppercase tracking-tighter mb-8">
            Account Control & Security
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
            <!-- Profile Info -->
            <div class="space-y-6">
                <h4 class="text-lg font-bold text-cpr-text-muted uppercase tracking-widest border-b border-cpr-border pb-2">
                    Profile Data
                </h4>
                <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    @method('patch')

                    <div>
                        <x-input-label for="name" :value="__('User Handle')" class="text-xs uppercase text-cpr-teal" />
                        <x-text-input id="name" name="name" type="text" class="cyber-input-field mt-1 w-full" :value="old('name', $user->name)" required autocomplete="name" />
                        <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Agent ID (Email)')" class="text-xs uppercase text-cpr-teal" />
                        <x-text-input id="email" name="email" type="email" class="cyber-input-field mt-1 w-full" :value="old('email', $user->email)" required autocomplete="username" />
                        <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase" :messages="$errors->get('email')" />
                    </div>

                    <button type="submit" class="cyber-button bg-cpr-teal-dark border-cpr-teal-dark w-full uppercase font-bold py-2 hover:bg-cpr-teal text-black">
                        Update Profile
                    </button>
                </form>
            </div>

            <!-- Security/Password -->
            <div class="space-y-6">
                <h4 class="text-lg font-bold text-cpr-text-muted uppercase tracking-widest border-b border-cpr-border pb-2">
                    Change Password
                </h4>
                <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                    @csrf
                    @method('put')

                    <div>
                        <x-input-label for="update_password_current_password" :value="__('Current Password')" class="text-xs uppercase text-cpr-teal" />
                        <x-text-input id="update_password_current_password" name="current_password" type="password" class="cyber-input-field mt-1 w-full" autocomplete="current-password" />
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1 text-cpr-red text-[10px] uppercase" />
                    </div>

                    <div>
                        <x-input-label for="update_password_password" :value="__('New Password')" class="text-xs uppercase text-cpr-teal" />
                        <x-text-input id="update_password_password" name="password" type="password" class="cyber-input-field mt-1 w-full" autocomplete="new-password" />
                        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1 text-cpr-red text-[10px] uppercase" />
                    </div>

                    <div>
                        <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" class="text-xs uppercase text-cpr-teal" />
                        <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="cyber-input-field mt-1 w-full" autocomplete="new-password" />
                        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1 text-cpr-red text-[10px] uppercase" />
                    </div>

                    <button type="submit" class="cyber-button bg-cpr-teal-dark border-cpr-teal-dark w-full uppercase font-bold py-2 hover:bg-cpr-teal text-black">
                        Update
                    </button>
                </form>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="mt-12 pt-8 border-t border-cpr-red-dark/30">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 bg-cpr-red-dark/10 p-6 border border-cpr-red-dark/20">
                <div>
                    <h4 class="text-lg font-bold text-cpr-red uppercase tracking-widest">Terminate Account</h4>
                    <p class="text-xs text-cpr-red/60 uppercase mt-1">Warning: This action is permanent and will wipe all Agent sync data.</p>
                </div>

                <button
                    x-data=""
                    x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
                    class="cyber-button bg-cpr-red-dark border-cpr-red-dark px-8 py-2 uppercase font-bold hover:bg-cpr-red whitespace-nowrap"
                >
                    Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

<x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
    <form method="post" action="{{ route('profile.destroy') }}" class="p-6 bg-cpr-black-deep border-2 border-cpr-red">
        @csrf
        @method('delete')

        <h2 class="text-2xl font-bold text-cpr-red uppercase tracking-tighter">
            Confirm Account Termination
        </h2>

        <p class="mt-4 text-sm text-cpr-text-muted uppercase tracking-widest">
            Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.
        </p>

        <div class="mt-6">
            <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

            <x-text-input
                id="password"
                name="password"
                type="password"
                class="cyber-input-field mt-1 block w-3/4"
                placeholder="{{ __('Enter Encryption Key') }}"
            />

            <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-cpr-red text-[10px] uppercase" />
        </div>

        <div class="mt-6 flex justify-end gap-4">
            <button type="button" x-on:click="$dispatch('close')" class="cyber-button bg-cpr-black-elevated border-cpr-border px-6 py-2 uppercase text-xs">
                Abort
            </button>

            <button type="submit" class="cyber-button bg-cpr-red-dark border-cpr-red-dark px-6 py-2 uppercase text-xs hover:bg-cpr-red">
                Terminate Account
            </button>
        </div>
    </form>
</x-modal>

<script @cspNonce>
    const actorSelection = document.getElementById('actor_selection');
    if (actorSelection) {
        const syncIds = () => {
            const val = actorSelection.value;
            if (val && val.includes('|')) {
                let [actorId, tokenId] = val.split('|');
                if (actorId.startsWith('Actor.')) {
                    actorId = actorId.replace('Actor.', '');
                }
                document.getElementById('default_actor_id').value = actorId;
                document.getElementById('default_token_id').value = tokenId !== 'null' ? tokenId : '';
            }
        };

        actorSelection.addEventListener('change', syncIds);

        // Initialize on load
        syncIds();
    }
</script>
</div>
@endsection
