<x-guest-layout>
    <div class="w-full mb-6">
        <h3 class="text-2xl font-bold mb-2 text-cpr-yellow uppercase tracking-widest border-b border-cpr-yellow/30 pb-2">
            Authentication Required</h3>
        <div class="flex justify-between items-center">
            <p class="text-[12px] text-cpr-yellow/80 uppercase italic">
                Note: Your data is not used for any purpose other than logging in. No RABIDS here.
            </p>
        </div>
    </div>

    <div class="flex flex-col md:flex-row items-center md:items-stretch gap-0 md:gap-6">
        <div class="w-full md:w-1/2 hidden md:block">
            <img src="{{ asset('images/external/splash-square.png') }}" alt="Cyberpunk Red Logo"
                 class="w-full h-full bg-black object-contain rounded-lg border border-cpr-border">
        </div>
        <div class="w-full md:w-1/2 max-w-xl">
            <div class="p-6 bg-cpr-black-deep/50 rounded-lg border border-cpr-border">
                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')"/>

                <form id="login-form" method="POST" action="{{ route('login') }}" class="space-y-6">
                    @csrf

                    <!-- Email Address -->
                    <div>
                        <x-input-label for="email" :value="__('Email')" class="text-xs uppercase text-cpr-text-muted"/>
                        <x-text-input id="email" class="cyber-input-field mt-1" type="email" name="email"
                                      :value="old('email')" required autofocus autocomplete="username"/>
                        <x-input-error :messages="$errors->get('email')" class="mt-2"/>
                    </div>

                    <!-- Password -->
                    <div>
                        <x-input-label for="password" :value="__('Password')"
                                       class="text-xs uppercase text-cpr-text-muted"/>
                        <x-text-input id="password" class="cyber-input-field mt-1"
                                      type="password"
                                      name="password"
                                      required autocomplete="current-password"/>
                        <x-input-error :messages="$errors->get('password')" class="mt-2"/>
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <input id="remember_me" type="checkbox"
                               class="rounded bg-cpr-black-elevated border-cpr-border text-cpr-pink-dark focus:ring-cpr-pink"
                               name="remember">
                        <label for="remember_me" class="ms-2 text-sm text-cpr-text-muted italic">Stay connected</label>
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
                            Initiate Link-up
                        </button>
                    </div>

                    <div class="flex flex-col gap-4">
                        @if (Route::has('password.request'))
                            <a class="text-xs text-center text-cpr-text-dim hover:text-cpr-yellow uppercase transition-colors"
                               href="{{ route('password.request') }}">
                                {{ __('Forgot access code?') }}
                            </a>
                        @endif

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                               class="cyber-button bg-cpr-black-elevated border-cpr-border text-lg px-12 py-4 uppercase font-bold text-center">
                                Register Bio-ID
                            </a>
                        @endif
                    </div>
                </form>
                <div class="text-center mt-4">
                    <a href="{{ route('how-to-use') }}"
                       class="text-[12px] text-cpr-red hover:text-cpr-yellow uppercase font-bold transition-colors border-b border-cpr-red/300 pb-1">
                        How to Use
                    </a>
                </div>
                {{-- Ko-fi support button — inside the box, below "How to Use". Only on the
     official hosted instance; self-hosted / local instances hide it here
     (it stays on the How to Use page). Colour matches the dark panel. --}}
                @if (request()->getHost() === 'agent.surrealdisco.co.uk')
                    <div class="flex justify-center mt-8">
                        <p class="text-cpr-text-muted text-xs italic block max-w-md">
                            Tips are always appreciated but never required, they will go towards the running of the server this is hosted on.
                        </p>
                    </div>
                    <div class="flex justify-center mt-8">
                        <script type='text/javascript' src='https://storage.ko-fi.com/cdn/widget/Widget_2.js' @cspNonce></script>
                        <script type='text/javascript' @cspNonce>
                            kofiwidget2.init('Support me on Ko-fi', '#141414', 'V8M8238Q2V');
                            kofiwidget2.draw();
                        </script>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-guest-layout>
