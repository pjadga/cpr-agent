<x-guest-layout>
    <div class="max-w-2xl mx-auto">
        <h2 class="text-3xl font-bold mb-6 glow-red text-cpr-red uppercase tracking-tighter border-b-2 border-cpr-red-dark pb-2">
            Profile Synchronization
        </h2>

        <p class="mb-8 text-cpr-text-muted italic">
            First time link detected. Please synchronize your Agent interface with a Foundry instance and select your default biometric signature (Actor).
        </p>

        <form method="POST" action="{{ route('profile-setup.store') }}" class="space-y-8">
            @csrf

            <!-- Default Actor ID -->
            <div>
                <x-input-label for="actor_selection" :value="__('Primary Biometric Signature (Actor)')" class="text-xs uppercase text-cpr-teal mb-1" />
                <select id="actor_selection" class="cyber-select-field">
                    <option value="" disabled {{ !$user->default_actor_id ? 'selected' : '' }}>Select Biometric Data</option>
                    @foreach($actors as $actor)
                        <option value="{{ $actor['actorId'] }}|{{ $actor['tokenId'] }}" {{ $user->default_actor_id == $actor["actorId"] && $user->default_token_id == $actor["tokenId"] ? 'selected' : '' }} class="cyber-select-option">
                            {{ $actor["name"] }} [{{ $actor["actorId"] }}]
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="default_actor_id" id="default_actor_id" value="{{ $user->default_actor_id }}">
                <input type="hidden" name="default_token_id" id="default_token_id" value="{{ $user->default_token_id }}">
                <x-input-error :messages="$errors->get('default_actor_id')" class="mt-2 text-cpr-red text-xs uppercase" />
                <x-input-error :messages="$errors->get('default_token_id')" class="mt-2 text-cpr-red text-xs uppercase" />
            </div>

            <script @cspNonce>
                document.getElementById('actor_selection').addEventListener('change', function() {
                    let [actorId, tokenId] = this.value.split('|');
                    if (actorId.startsWith('Actor.')) {
                        actorId = actorId.replace('Actor.', '');
                    }
                    document.getElementById('default_actor_id').value = actorId;
                    document.getElementById('default_token_id').value = tokenId;
                });
            </script>

            <div class="pt-4 border-t border-cpr-border">
                <button type="submit" class="cyber-button bg-cpr-teal-dark border-cpr-teal w-full uppercase font-bold tracking-widest py-3 text-black">
                    Commit Synchronization
                </button>
            </div>
        </form>
    </div>
</x-guest-layout>
