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
                    <option value="" disabled {{ !$user->client_id ? 'selected' : '' }}>Select Interface ID</option>
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
                <x-input-label for="default_actor_id" :value="__('Primary Biometric Signature (Actor)')" class="text-xs uppercase text-lime-400 mb-1" />
                <select id="default_actor_id" name="default_actor_id" class="cyber-select-field">
                    <option value="" disabled {{ !$user->default_actor_id ? 'selected' : '' }}>Select Biometric Data</option>
                    @foreach($actors as $id => $name)
                        <option value="{{ $id }}" {{ $user->default_actor_id == $id ? 'selected' : '' }} class="cyber-select-option">
                            {{ $name }} [{{ $id }}]
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('default_actor_id')" class="mt-2 text-red-500 text-xs uppercase" />
            </div>

            <div class="pt-4 border-t border-gray-800">
                <button type="submit" class="cyber-button bg-lime-600 border-lime-500 w-full uppercase font-bold tracking-widest py-3">
                    Commit Synchronization
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>
