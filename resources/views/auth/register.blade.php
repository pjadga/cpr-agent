<x-guest-layout>
    <div class="flex flex-col items-center">
        <div class="w-full max-w-xl">
            <div class="p-6 bg-cpr-black-deep/50 rounded-lg border border-cpr-border">
                <h3 class="text-xl font-bold mb-2 text-cpr-pink uppercase tracking-widest border-b border-cpr-pink/30 pb-2">Bio-ID Registration</h3>
                <p class="text-[10px] text-cpr-yellow/80 uppercase mb-6 italic">
                    Note: Use the same credentials as your Foundry VTT account to enable biometric synchronization.
                </p>

                <form method="POST" action="{{ route('register') }}" class="space-y-4">
                    @csrf

                    <!-- Name -->
                    <div>
                        <x-input-label for="name" :value="__('Operator Name')" class="text-xs uppercase text-cpr-text-muted" />
                        <x-text-input id="name" class="cyber-input-field mt-1" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" :value="__('Agent ID (Email)')" class="text-xs uppercase text-cpr-text-muted" />
                        <x-text-input id="email" class="cyber-input-field mt-1" type="email" name="email" :value="old('email')" required autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div>
                        <x-input-label for="password" :value="__('Access Code (Password)')" class="text-xs uppercase text-cpr-text-muted" />
                        <x-text-input id="password" class="cyber-input-field mt-1"
                                        type="password"
                                        name="password"
                                        required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <x-input-label for="password_confirmation" :value="__('Verify Access Code')" class="text-xs uppercase text-cpr-text-muted" />
                        <x-text-input id="password_confirmation" class="cyber-input-field mt-1"
                                        type="password"
                                        name="password_confirmation" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <div class="flex flex-col gap-4 pt-4">
                        <button type="submit" class="cyber-button bg-cpr-red border-cpr-red w-full uppercase font-black text-lg py-3">
                            Authorize Connection
                        </button>

                        <a class="text-xs text-center text-cpr-text-dim hover:text-cpr-pink uppercase transition-colors" href="{{ route('login') }}">
                            {{ __('Already registered? Login here') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
