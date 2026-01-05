<x-guest-layout>
    <div class="flex flex-col md:flex-row gap-8 items-center">
        <div class="md:w-1/2">
            <h2 class="text-4xl font-bold mb-4 glow-red text-red-500 uppercase tracking-tighter">Join the Net</h2>
            <p class="text-gray-300 mb-6 leading-relaxed">
                New to Night City? Register your biometric data and secure your neural link.
                Your journey into the neon abyss starts here.
            </p>
            <div class="rounded shadow-2xl border-2 border-red-900 overflow-hidden group">
                <img src="https://images.unsplash.com/photo-1550745165-9bc0b252726f?q=80&w=2070&auto=format&fit=crop"
                     alt="Cyberpunk Technology"
                     class="w-full h-auto grayscale hover:grayscale-0 transition-all duration-500">
            </div>
        </div>

        <div class="md:w-1/2 w-full">
            <div class="p-6 bg-gray-900/50 rounded-lg border border-gray-800">
                <h3 class="text-xl font-bold mb-6 text-pink-500 uppercase tracking-widest border-b border-pink-500/30 pb-2">Bio-ID Registration</h3>

                <form method="POST" action="{{ route('register') }}" class="space-y-4">
                    @csrf

                    <!-- Name -->
                    <div>
                        <x-input-label for="name" :value="__('Operator Name')" class="text-xs uppercase text-gray-400" />
                        <x-text-input id="name" class="cyber-input-field mt-1" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" :value="__('Neural ID (Email)')" class="text-xs uppercase text-gray-400" />
                        <x-text-input id="email" class="cyber-input-field mt-1" type="email" name="email" :value="old('email')" required autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div>
                        <x-input-label for="password" :value="__('Access Code (Password)')" class="text-xs uppercase text-gray-400" />
                        <x-text-input id="password" class="cyber-input-field mt-1"
                                        type="password"
                                        name="password"
                                        required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <x-input-label for="password_confirmation" :value="__('Verify Access Code')" class="text-xs uppercase text-gray-400" />
                        <x-text-input id="password_confirmation" class="cyber-input-field mt-1"
                                        type="password"
                                        name="password_confirmation" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <div class="flex flex-col gap-4 pt-4">
                        <button type="submit" class="cyber-button bg-red-600 border-red-500 w-full uppercase font-black text-lg py-3">
                            Authorize Connection
                        </button>

                        <a class="text-xs text-center text-gray-500 hover:text-pink-500 uppercase transition-colors" href="{{ route('login') }}">
                            {{ __('Already registered? Login here') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
