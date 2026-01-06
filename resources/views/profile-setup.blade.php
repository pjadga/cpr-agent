<x-guest-layout>
    <div class="max-w-2xl mx-auto">
        <h2 class="text-3xl font-bold mb-6 glow-lime text-lime-500 uppercase tracking-tighter border-b-2 border-lime-900 pb-2">
            Profile Synchronization
        </h2>

        <p class="mb-8 text-gray-400 italic">
            First time link detected. Please synchronize your neural interface with a Foundry instance and select your default biometric signature (Actor).
        </p>

        <form method="POST" action="{{ route('profile-setup.store') }}" class="space-y-8">
            @csrf

            <!-- Client ID -->
            <div>
                <x-input-label for="client_id" :value="__('Foundry Client Interface')" class="text-xs uppercase text-lime-400 mb-1" />
                <select id="client_id" name="client_id" class="cyber-select-field">
                    <option value="" disabled {{ !$user->client_id ? 'selected' : '' }}>Select Client Interface ID</option>
                    @foreach($clientIds as $id => $name)
                        <option value="{{ $id }}" {{ $user->client_id == $id ? 'selected' : '' }} class="cyber-select-option">
                            {{ $name }} [{{ $id }}]
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('client_id')" class="mt-2 text-red-500 text-xs uppercase" />
            </div>

            <!-- Default Actor ID -->
            <div>
                <x-input-label for="actor_selection" :value="__('Primary Biometric Signature (Actor)')" class="text-xs uppercase text-lime-400 mb-1" />
                <select id="actor_selection" class="cyber-select-field" onchange="updateActorTokens(this)">
                    <option value="" disabled {{ !$user->default_actor_id ? 'selected' : '' }}>Select Biometric Data</option>
                    @foreach($actors as $actor)
                        <option value="{{ $actor['actorId'] }}|{{ $actor['tokenId'] }}" {{ $user->default_actor_id == $actor["actorId"] && $user->default_token_id == $actor["tokenId"] ? 'selected' : '' }} class="cyber-select-option">
                            {{ $actor["name"] }} [{{ $actor["actorId"] }}]
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="default_actor_id" id="default_actor_id" value="{{ $user->default_actor_id }}">
                <input type="hidden" name="default_token_id" id="default_token_id" value="{{ $user->default_token_id }}">
                <x-input-error :messages="$errors->get('default_actor_id')" class="mt-2 text-red-500 text-xs uppercase" />
                <x-input-error :messages="$errors->get('default_token_id')" class="mt-2 text-red-500 text-xs uppercase" />
            </div>

            <script>
                function updateActorTokens(select) {
                    const [actorId, tokenId] = select.value.split('|');
                    document.getElementById('default_actor_id').value = actorId;
                    document.getElementById('default_token_id').value = tokenId;
                }
            </script>

            <div class="pt-4 border-t border-gray-800">
                <button type="submit" class="cyber-button bg-lime-600 border-lime-500 w-full uppercase font-bold tracking-widest py-3">
                    Commit Synchronization
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>
