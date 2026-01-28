@extends('layouts.cyberpunk')

@section('content')
<div class="max-w-4xl mx-auto p-8">
    <div class="flex justify-between items-center mb-8 border-b-2 border-gray-800 pb-4">
        <h2 class="text-4xl font-bold glow-lime text-lime-500 uppercase tracking-tighter">
            System Configuration & Neural Link
        </h2>
        <div class="flex gap-4">
            <a href="{{ route('dashboard') }}" class="cyber-button bg-gray-800 border-gray-700 text-xs px-4 py-2 uppercase w-32 text-center">
                Return
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="cyber-button bg-red-900 border-red-800 text-xs px-4 py-2 uppercase hover:bg-red-700 w-32">
                    Disconnect
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-lime-900/50 border border-lime-500 text-lime-400 p-4 mb-8 uppercase text-xs tracking-widest">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
        <!-- Global App Configuration -->
        <div class="space-y-6">
            <h3 class="text-xl font-bold text-pink-500 uppercase tracking-widest border-b border-pink-500/30 pb-2">
                Core System Settings
            </h3>
            <form action="{{ route('config.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <div class="flex items-center gap-2">
                        <x-input-label for="api_key" :value="__('API Key')" class="text-xs uppercase text-gray-400" />
                        <div class="group relative flex items-center">
                            <i data-lucide="help-circle" class="w-3 h-3 text-gray-500 hover:text-pink-500 cursor-help transition-colors"></i>
                            <div class="absolute left-full ml-2 w-48 p-2 bg-gray-900 border border-pink-500 text-[10px] text-gray-200 uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                Must be the same key added to your Foundry ThreeHats module.
                            </div>
                        </div>
                    </div>
                    <x-text-input type="text" name="api_key" id="api_key" value="{{ old('api_key', $config->api_key) }}" class="cyber-input-field mt-1 w-full" />
                </div>
{{--                <div>--}}
{{--                    <x-input-label for="base_url" :value="__('Relay Base URL')" class="text-xs uppercase text-gray-400" />--}}
{{--                    <x-text-input type="text" name="base_url" id="base_url" value="{{ old('base_url', $config->base_url ?? config('services.foundry.base_url')) }}" class="cyber-input-field mt-1 w-full" />--}}
{{--                </div>--}}
                <div>
                    <div class="flex items-center gap-2">
                        <x-input-label for="foundry_url" :value="__('Foundry URL')" class="text-xs uppercase text-gray-400" />
                        <div class="group relative flex items-center">
                            <i data-lucide="help-circle" class="w-3 h-3 text-gray-500 hover:text-pink-500 cursor-help transition-colors"></i>
                            <div class="absolute left-full ml-2 w-48 p-2 bg-gray-900 border border-pink-500 text-[10px] text-gray-200 uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                The full URL of your Foundry VTT instance.
                            </div>
                        </div>
                    </div>
                    <x-text-input type="text" name="foundry_url" id="foundry_url" value="{{ old('foundry_url', $config->foundry_url) }}" class="cyber-input-field mt-1 w-full" />
                </div>
                <button type="submit" class="cyber-button bg-pink-600 border-pink-500 w-full uppercase font-bold py-2">
                    Update Core Settings
                </button>
            </form>
        </div>

        <!-- User Profile Synchronization -->
        <div class="space-y-6">
            <div class="flex justify-between items-center border-b border-lime-500/30 pb-2">
                <h3 class="text-xl font-bold text-lime-500 uppercase tracking-widest">
                    User Neural Link
                </h3>
                <a href="{{ route('settings') }}" class="text-lime-500 hover:text-lime-400 transition-colors" title="Refresh Neural Link">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </a>
            </div>
            @if(!$config->api_key || (!$config->base_url && !config('services.foundry.base_url')))
                <div class="bg-red-900/30 border border-red-800 text-red-400 p-4 text-xs italic">
                    SYSTEM OFFLINE: Configure Core System Settings to enable User Neural Link synchronization.
                </div>
            @else
                <form method="POST" action="{{ route('profile-setup.store') }}" class="space-y-4">
                    @csrf
                    <!-- Client ID -->
                    <div>
                        <div class="flex items-center gap-2">
                            <x-input-label for="client_id" :value="__('Foundry Client Interface')" class="text-xs uppercase text-lime-400" />
                            <div class="group relative flex items-center">
                                <i data-lucide="help-circle" class="w-3 h-3 text-gray-500 hover:text-lime-500 cursor-help transition-colors"></i>
                                <div class="absolute left-full ml-2 w-48 p-2 bg-gray-900 border border-lime-500 text-[10px] text-gray-200 uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                    Appears once the GM account is connected and running in Foundry.
                                </div>
                            </div>
                        </div>
                        <select id="client_id" name="client_id" class="cyber-select-field mt-1 text-base">
                            <option value="" disabled {{ !$user->client_id ? 'selected' : '' }}>Select Client ID</option>
                            @foreach($clientIds as $id => $name)
                                <option value="{{ $id }}" {{ $user->client_id == $id ? 'selected' : '' }} class="cyber-select-option">
                                    {{ $name }} [{{ $id }}]
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('client_id')" class="mt-1 text-red-500 text-[10px] uppercase" />
                    </div>

                    <!-- Default Actor ID -->
                    <div>
                        <div class="flex items-center gap-2">
                            <x-input-label for="actor_selection" :value="__('Primary Biometric Signature (Actor)')" class="text-xs uppercase text-lime-400" />
                            <div class="group relative flex items-center">
                                <i data-lucide="help-circle" class="w-3 h-3 text-gray-500 hover:text-lime-500 cursor-help transition-colors"></i>
                                <div class="absolute left-full ml-2 w-48 p-2 bg-gray-900 border border-lime-500 text-[10px] text-gray-200 uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                    Loads playable characters and player-owned characters from the current session.
                                </div>
                            </div>
                        </div>
                        <select id="actor_selection" class="cyber-select-field mt-1 text-base">
                            <option value="" disabled {{ !$user->default_actor_id ? 'selected' : '' }}>Select Biometric Data</option>
                            @foreach($actors as $actor)
                                <option value="{{ $actor['actorId'] }}|{{ $actor['tokenId'] }}" {{ $user->default_actor_id == $actor["actorId"] && $user->default_token_id == $actor["tokenId"] ? 'selected' : '' }} class="cyber-select-option">
                                    {{ $actor["name"] }} [{{ $actor["actorId"] }}]
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="default_actor_id" id="default_actor_id" value="{{ $user->default_actor_id }}">
                        <input type="hidden" name="default_token_id" id="default_token_id" value="{{ $user->default_token_id }}">
                        <x-input-error :messages="$errors->get('default_actor_id')" class="mt-1 text-red-500 text-[10px] uppercase" />
                    </div>


                    <button type="submit" class="cyber-button bg-lime-600 border-lime-500 w-full uppercase font-bold py-2">
                        Commit Neural Link
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Account Management -->
    <div class="mt-12 pt-8 border-t-2 border-gray-800">
        <h3 class="text-2xl font-bold text-blue-500 uppercase tracking-tighter mb-8">
            Account Control & Security
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
            <!-- Profile Info -->
            <div class="space-y-6">
                <h4 class="text-lg font-bold text-gray-400 uppercase tracking-widest border-b border-gray-800 pb-2">
                    Profile Data
                </h4>
                <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
                    @csrf
                    @method('patch')

                    <div>
                        <x-input-label for="name" :value="__('User Handle')" class="text-xs uppercase text-gray-500" />
                        <x-text-input id="name" name="name" type="text" class="cyber-input-field mt-1 w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                        <x-input-error class="mt-1 text-red-500 text-[10px] uppercase" :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Neural ID (Email)')" class="text-xs uppercase text-gray-500" />
                        <x-text-input id="email" name="email" type="email" class="cyber-input-field mt-1 w-full" :value="old('email', $user->email)" required autocomplete="username" />
                        <x-input-error class="mt-1 text-red-500 text-[10px] uppercase" :messages="$errors->get('email')" />
                    </div>

                    <button type="submit" class="cyber-button bg-blue-900 border-blue-800 w-full uppercase font-bold py-2 hover:bg-blue-700">
                        Update Profile
                    </button>
                </form>
            </div>

            <!-- Security/Password -->
            <div class="space-y-6">
                <h4 class="text-lg font-bold text-gray-400 uppercase tracking-widest border-b border-gray-800 pb-2">
                    Security Encryption
                </h4>
                <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                    @csrf
                    @method('put')

                    <div>
                        <x-input-label for="update_password_current_password" :value="__('Current Encryption Key')" class="text-xs uppercase text-gray-500" />
                        <x-text-input id="update_password_current_password" name="current_password" type="password" class="cyber-input-field mt-1 w-full" autocomplete="current-password" />
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1 text-red-500 text-[10px] uppercase" />
                    </div>

                    <div>
                        <x-input-label for="update_password_password" :value="__('New Encryption Key')" class="text-xs uppercase text-gray-500" />
                        <x-text-input id="update_password_password" name="password" type="password" class="cyber-input-field mt-1 w-full" autocomplete="new-password" />
                        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1 text-red-500 text-[10px] uppercase" />
                    </div>

                    <div>
                        <x-input-label for="update_password_password_confirmation" :value="__('Confirm Encryption Key')" class="text-xs uppercase text-gray-500" />
                        <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" class="cyber-input-field mt-1 w-full" autocomplete="new-password" />
                        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1 text-red-500 text-[10px] uppercase" />
                    </div>

                    <button type="submit" class="cyber-button bg-blue-900 border-blue-800 w-full uppercase font-bold py-2 hover:bg-blue-700">
                        Update Encryption
                    </button>
                </form>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="mt-12 pt-8 border-t border-red-900/30">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 bg-red-900/10 p-6 border border-red-900/20">
                <div>
                    <h4 class="text-lg font-bold text-red-500 uppercase tracking-widest">Terminate Account</h4>
                    <p class="text-xs text-red-400/60 uppercase mt-1">Warning: This action is permanent and will wipe all neural link data.</p>
                </div>

                <button
                    x-data=""
                    x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
                    class="cyber-button bg-red-900 border-red-800 px-8 py-2 uppercase font-bold hover:bg-red-700 whitespace-nowrap"
                >
                    Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

<x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
    <form method="post" action="{{ route('profile.destroy') }}" class="p-6 bg-gray-900 border-2 border-red-600">
        @csrf
        @method('delete')

        <h2 class="text-2xl font-bold text-red-600 uppercase tracking-tighter">
            Confirm Account Termination
        </h2>

        <p class="mt-4 text-sm text-gray-400 uppercase tracking-widest">
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

            <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-red-500 text-[10px] uppercase" />
        </div>

        <div class="mt-6 flex justify-end gap-4">
            <button type="button" x-on:click="$dispatch('close')" class="cyber-button bg-gray-800 border-gray-700 px-6 py-2 uppercase text-xs">
                Abort
            </button>

            <button type="submit" class="cyber-button bg-red-900 border-red-800 px-6 py-2 uppercase text-xs hover:bg-red-600">
                Terminate Account
            </button>
        </div>
    </form>
</x-modal>

<script @cspNonce>
    const actorSelection = document.getElementById('actor_selection');
    if (actorSelection) {
        actorSelection.addEventListener('change', function() {
            const [actorId, tokenId] = this.value.split('|');
            document.getElementById('default_actor_id').value = actorId;
            document.getElementById('default_token_id').value = tokenId;
        });
    }
</script>
@endsection
