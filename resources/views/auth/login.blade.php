<x-guest-layout>
    <div class="flex flex-col md:flex-row gap-8 items-center">
        <div class="md:w-1/2">
            <h2 class="text-4xl font-bold mb-4 glow-red text-red-500 uppercase tracking-tighter">Welcome to Night City</h2>
            <p class="text-gray-300 mb-6 leading-relaxed">
                Connect your interface. Log in to access your character data and neural links.
                The streets of 2077 don't wait for anyone.
            </p>
            <div class="rounded shadow-2xl border-2 border-red-900 overflow-hidden group">
                <img src="https://images.unsplash.com/photo-1605810230434-7631ac76ec81?q=80&w=2070&auto=format&fit=crop"
                     alt="Cyberpunk Cityscape"
                     class="w-full h-auto grayscale hover:grayscale-0 transition-all duration-500">
            </div>
        </div>

        <div class="md:w-1/2 w-full">
            <div class="p-6 bg-gray-900/50 rounded-lg border border-gray-800">
                <h3 class="text-xl font-bold mb-2 text-pink-500 uppercase tracking-widest border-b border-pink-500/30 pb-2">Authentication Required</h3>
                <p class="text-[10px] text-yellow-500/80 uppercase mb-6 italic">
                    Note: Ensure your credentials match your Foundry VTT login for seamless neural synchronization.
                </p>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="space-y-6">
                    @csrf

                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" :value="__('Neural ID (Email)')" class="text-xs uppercase text-gray-400" />
                        <x-text-input id="email" class="cyber-input-field mt-1" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div>
                        <x-input-label for="password" :value="__('Access Code (Password)')" class="text-xs uppercase text-gray-400" />
                        <x-text-input id="password" class="cyber-input-field mt-1"
                                        type="password"
                                        name="password"
                                        required autocomplete="current-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <input id="remember_me" type="checkbox" class="rounded bg-gray-800 border-gray-700 text-pink-600 focus:ring-pink-500" name="remember">
                        <label for="remember_me" class="ms-2 text-sm text-gray-400 italic">Stay connected</label>
                    </div>

                    <div class="flex flex-col gap-4">
                        <button type="submit" class="cyber-button bg-red-600 border-red-500 w-full uppercase font-black text-lg py-3">
                            Initiate Link-up
                        </button>

                        @if (Route::has('password.request'))
                            <a class="text-xs text-center text-gray-500 hover:text-pink-500 uppercase transition-colors" href="{{ route('password.request') }}">
                                {{ __('Forgot access code?') }}
                            </a>
                        @endif

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="cyber-button bg-gray-800 border-gray-700 text-lg px-12 py-4 uppercase font-bold text-center">
                                Register Bio-ID
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
