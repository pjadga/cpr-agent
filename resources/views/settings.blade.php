@extends('layouts.cyberpunk')

@section('content')
<div class="max-w-4xl mx-auto p-8">
    <div class="flex justify-between items-center mb-8 border-b-2 border-gray-800 pb-4">
        <h2 class="text-4xl font-bold glow-lime text-lime-500 uppercase tracking-tighter">
            System Configuration & Neural Link
        </h2>
        <a href="{{ route('dashboard') }}" class="cyber-button bg-gray-800 border-gray-700 text-xs px-4 py-2 uppercase">
            Return to Interface
        </a>
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
                    <x-input-label for="api_key" :value="__('API Key')" class="text-xs uppercase text-gray-400" />
                    <x-text-input type="text" name="api_key" id="api_key" value="{{ old('api_key', $config->api_key) }}" class="cyber-input-field mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="base_url" :value="__('Relay Base URL')" class="text-xs uppercase text-gray-400" />
                    <x-text-input type="text" name="base_url" id="base_url" value="{{ old('base_url', $config->base_url) }}" class="cyber-input-field mt-1 w-full" />
                </div>
                <div>
                    <x-input-label for="foundry_url" :value="__('Foundry URL')" class="text-xs uppercase text-gray-400" />
                    <x-text-input type="text" name="foundry_url" id="foundry_url" value="{{ old('foundry_url', $config->foundry_url) }}" class="cyber-input-field mt-1 w-full" />
                </div>
                <button type="submit" class="cyber-button bg-pink-600 border-pink-500 w-full uppercase font-bold py-2">
                    Update Core Settings
                </button>
            </form>
        </div>

        <!-- User Profile Synchronization -->
        <div class="space-y-6">
            <h3 class="text-xl font-bold text-lime-500 uppercase tracking-widest border-b border-lime-500/30 pb-2">
                User Neural Link
            </h3>
            @if(!$config->api_key || !$config->base_url)
                <div class="bg-red-900/30 border border-red-800 text-red-400 p-4 text-xs italic">
                    SYSTEM OFFLINE: Configure Core System Settings to enable User Neural Link synchronization.
                </div>
            @else
                <form method="POST" action="{{ route('profile-setup.store') }}" class="space-y-4">
                    @csrf
                    <!-- Client ID -->
                    <div>
                        <x-input-label for="client_id" :value="__('Foundry Client Interface')" class="text-xs uppercase text-lime-400" />
                        <select id="client_id" name="client_id" class="cyber-select-field mt-1">
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
                        <x-input-label for="actor_selection" :value="__('Primary Biometric Signature (Actor)')" class="text-xs uppercase text-lime-400" />
                        <select id="actor_selection" class="cyber-select-field mt-1">
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
</div>

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
