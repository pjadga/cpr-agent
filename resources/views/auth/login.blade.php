<x-guest-layout>
    <div class="flex flex-col items-center">
        <div class="w-full max-w-xl">
            <div class="p-6 bg-cpr-black-deep/50 rounded-lg border border-cpr-border">
                <h3 class="text-xl font-bold mb-2 text-cpr-pink uppercase tracking-widest border-b border-cpr-pink/30 pb-2">Authentication Required</h3>
                <div class="flex justify-between items-center mb-6">
                    <p class="text-[10px] text-cpr-yellow/80 uppercase italic">
                        Note: Ensure your credentials match your Foundry VTT login for seamless synchronization.
                    </p>
                    <a href="{{ route('how-to-use') }}" class="text-[10px] text-cpr-red hover:text-cpr-red uppercase font-bold transition-colors">
                        How to Use
                    </a>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="space-y-6">
                    @csrf

                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" :value="__('Agent ID (Email)')" class="text-xs uppercase text-cpr-text-muted" />
                        <x-text-input id="email" class="cyber-input-field mt-1" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div>
                        <x-input-label for="password" :value="__('Access Code (Password)')" class="text-xs uppercase text-cpr-text-muted" />
                        <x-text-input id="password" class="cyber-input-field mt-1"
                                        type="password"
                                        name="password"
                                        required autocomplete="current-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <input id="remember_me" type="checkbox" class="rounded bg-cpr-black-elevated border-cpr-border text-cpr-pink-dark focus:ring-cpr-pink" name="remember">
                        <label for="remember_me" class="ms-2 text-sm text-cpr-text-muted italic">Stay connected</label>
                    </div>

                    <div class="flex flex-col gap-4">
                        <button type="submit" class="cyber-button bg-cpr-red border-cpr-red w-full uppercase font-black text-lg py-3">
                            Initiate Link-up
                        </button>

                        @if (Route::has('password.request'))
                            <a class="text-xs text-center text-cpr-text-dim hover:text-cpr-pink uppercase transition-colors" href="{{ route('password.request') }}">
                                {{ __('Forgot access code?') }}
                            </a>
                        @endif

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="cyber-button bg-cpr-black-elevated border-cpr-border text-lg px-12 py-4 uppercase font-bold text-center">
                                Register Bio-ID
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
