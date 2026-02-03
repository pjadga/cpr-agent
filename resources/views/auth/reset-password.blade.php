<x-guest-layout>
    <div class="max-w-md mx-auto">
        <h2 class="text-2xl font-bold mb-6 glow-red text-cpr-red uppercase tracking-tighter">Override Access Code</h2>

        <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Agent ID (Email)')" class="text-xs uppercase text-cpr-text-muted" />
                <x-text-input id="email" class="cyber-input-field mt-1" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div>
                <x-input-label for="password" :value="__('New Access Code')" class="text-xs uppercase text-cpr-text-muted" />
                <x-text-input id="password" class="cyber-input-field mt-1" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Confirm Password -->
            <div>
                <x-input-label for="password_confirmation" :value="__('Verify New Code')" class="text-xs uppercase text-cpr-text-muted" />

                <x-text-input id="password_confirmation" class="cyber-input-field mt-1"
                                    type="password"
                                    name="password_confirmation" required autocomplete="new-password" />

                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end pt-4">
                <x-primary-button class="w-full py-3">
                    {{ __('Apply Override') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>
