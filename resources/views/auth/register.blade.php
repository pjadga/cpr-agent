<x-guest-layout>
    <div class="w-full mb-6">
        <h3 class="text-xl font-bold mb-2 text-cpr-yellow uppercase tracking-widest border-b border-cpr-yellow/30 pb-2">
            Bio-ID Registration</h3>
        <p class="text-[10px] text-cpr-yellow/80 uppercase italic">
            Note: Use the same credentials as your Foundry VTT account to enable biometric synchronization.
        </p>
    </div>

    <div class="flex flex-col md:flex-row items-center md:items-stretch gap-0 md:gap-6">
        <div class="w-full md:w-1/2 hidden md:block">
            <img src="{{ asset('images/external/splash-square.png') }}" alt="Cyberpunk Red Logo"
                 class="w-full h-full rounded-lg border border-cpr-border bg-black object-contain">
        </div>
        <div class="w-full md:w-1/2 max-w-xl">
            <div class="p-6 bg-cpr-black-deep/50 rounded-lg border border-cpr-border">
                <form id="register-form" method="POST" action="{{ route('register') }}" class="space-y-4">
                    @csrf

                    <!-- Name -->
                    <div>
                        <x-input-label for="name" :value="__('Edgerunner Name')"
                                       class="text-xs uppercase text-cpr-text-muted"/>
                        <x-text-input id="name" class="cyber-input-field mt-1" type="text" name="name"
                                      :value="old('name')" required autofocus autocomplete="name"/>
                        <x-input-error :messages="$errors->get('name')" class="mt-2"/>
                    </div>

                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" :value="__('Email')" class="text-xs uppercase text-cpr-text-muted"/>
                        <x-text-input id="email" class="cyber-input-field mt-1" type="email" name="email"
                                      :value="old('email')" required autocomplete="username"/>
                        <x-input-error :messages="$errors->get('email')" class="mt-2"/>
                    </div>

                    <!-- Password -->
                    <div>
                        <x-input-label for="password" :value="__('Password')"
                                       class="text-xs uppercase text-cpr-text-muted"/>
                        <x-text-input id="password" class="cyber-input-field mt-1"
                                      type="password"
                                      name="password"
                                      required autocomplete="new-password"/>
                        <x-input-error :messages="$errors->get('password')" class="mt-2"/>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <x-input-label for="password_confirmation" :value="__('Verify Password')"
                                       class="text-xs uppercase text-cpr-text-muted"/>
                        <x-text-input id="password_confirmation" class="cyber-input-field mt-1"
                                      type="password"
                                      name="password_confirmation" required autocomplete="new-password"/>
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2"/>
                    </div>

                    <!-- Role: shapes the whole onboarding flow. Changeable later in Settings. -->
                    <div>
                        <x-input-label :value="__('Your Role')" class="text-xs uppercase text-cpr-text-muted"/>
                        <div class="grid grid-cols-2 gap-2 mt-1">
                            <label class="cursor-pointer">
                                <input type="radio" name="role" value="player" class="peer sr-only"
                                       {{ old('role', 'player') === 'player' ? 'checked' : '' }}>
                                <div class="border border-cpr-border rounded p-3 text-center uppercase text-xs tracking-widest text-cpr-text-dim
                                            peer-checked:border-cpr-teal peer-checked:text-cpr-teal peer-checked:bg-cpr-teal/10 transition-colors">
                                    <i class="fas fa-user-astronaut block text-lg mb-1"></i>
                                    Player
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="role" value="gm" class="peer sr-only"
                                       {{ old('role') === 'gm' ? 'checked' : '' }}>
                                <div class="border border-cpr-border rounded p-3 text-center uppercase text-xs tracking-widest text-cpr-text-dim
                                            peer-checked:border-cpr-red peer-checked:text-cpr-red peer-checked:bg-cpr-red/10 transition-colors">
                                    <i class="fas fa-crown block text-lg mb-1"></i>
                                    Game Master
                                </div>
                            </label>
                        </div>
                        <p class="text-[10px] text-cpr-text-dim mt-1 uppercase">
                            Not sure? Pick Player, you can change this later in Settings.
                        </p>
                        <x-input-error :messages="$errors->get('role')" class="mt-2"/>
                    </div>

                    <!-- Captcha -->
                    @if(config('services.turnstile.enabled'))
                        <div class="mt-4 flex flex-col items-center">
                            <x-turnstile/>
                            <x-input-error :messages="$errors->get('cf-turnstile-response')" class="mt-2"/>
                        </div>
                    @endif

                    <div class="mt-4 flex flex-col items-center">
                        <button type="submit"
                                class="cyber-button bg-cpr-red border-cpr-red w-full uppercase font-black text-lg py-3 mt-4">
                            Authorize Connection
                        </button>
                    </div>

                    <div class="flex flex-col gap-4 pt-4">
                        <a class="text-xs text-center text-cpr-text-dim hover:text-cpr-yellow uppercase transition-colors"
                           href="{{ route('login') }}">
                            {{ __('Already registered? Login here') }}
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
