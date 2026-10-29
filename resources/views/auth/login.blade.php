<x-guest-layout>
    <div class="w-full mb-6">
        <h3 class="text-2xl font-bold mb-2 text-cpr-yellow uppercase tracking-widest border-b border-cpr-yellow/30 pb-2">
            Authentication Required</h3>
        <div class="flex justify-between items-center">
            <p class="text-[12px] text-cpr-yellow/80 uppercase italic">
                Unlock with your username, PIN or pattern. Your gamemaster gave you a username.
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

                <div x-data="{ method: @js(old('method', 'username')) }">
                    <div class="grid grid-cols-3 gap-2 mb-6" role="tablist">
                        @foreach (['username' => 'Username', 'pin' => 'PIN', 'pattern' => 'Pattern'] as $key => $label)
                            <button type="button" role="tab" x-on:click="method = '{{ $key }}'"
                                    x-bind:aria-selected="method === '{{ $key }}'"
                                    x-bind:class="method === '{{ $key }}' ? 'border-cpr-teal text-cpr-teal bg-cpr-teal/10' : 'border-cpr-border text-cpr-text-dim'"
                                    class="border rounded p-2 text-center uppercase text-xs tracking-widest transition-colors">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <x-input-error :messages="$errors->get('value')" class="mb-4"/>
                    <x-input-error :messages="$errors->get('method')" class="mb-4"/>

                    <form method="POST" action="{{ route('login') }}" class="space-y-6" x-show="method === 'username'">
                        @csrf
                        <input type="hidden" name="method" value="username">
                        <div>
                            <x-input-label for="username" :value="__('Username')" class="text-xs uppercase text-cpr-text-muted"/>
                            <x-text-input id="username" class="cyber-input-field mt-1" type="password" name="value"
                                          required autofocus autocomplete="off" autocapitalize="none" spellcheck="false"/>
                            <p class="text-[10px] text-cpr-text-dim mt-1 uppercase">Your username signs you in on its own, so keep it to yourself.</p>
                        </div>
                        @include('auth.partials.login-footer')
                    </form>

                    <form method="POST" action="{{ route('login') }}" class="space-y-6" x-show="method === 'pin'" x-cloak>
                        @csrf
                        <input type="hidden" name="method" value="pin">
                        <div>
                            <x-input-label for="pin" :value="__('PIN')" class="text-xs uppercase text-cpr-text-muted"/>
                            <x-text-input id="pin" class="cyber-input-field mt-1 text-center tracking-[0.5em]" type="password" name="value"
                                          inputmode="numeric" pattern="[0-9]*" maxlength="12" autocomplete="off" required/>
                        </div>
                        @include('auth.partials.login-footer')
                    </form>

                    <form method="POST" action="{{ route('login') }}" class="space-y-4" x-show="method === 'pattern'" x-cloak>
                        @csrf
                        <input type="hidden" name="method" value="pattern">
                        <x-pattern-lock name="value" :submit="true"/>
                        <div class="flex items-center justify-center">
                            <input id="remember_pattern" type="checkbox" name="remember"
                                   class="rounded bg-cpr-black-elevated border-cpr-border text-cpr-pink-dark focus:ring-cpr-pink">
                            <label for="remember_pattern" class="ms-2 text-sm text-cpr-text-muted italic">Stay connected</label>
                        </div>
                        @if(config('services.turnstile.enabled'))
                            <div class="flex flex-col items-center"><x-turnstile/></div>
                        @endif
                    </form>

                    <div class="flex flex-col gap-2 mt-6">
                        <a class="text-xs text-center text-cpr-text-dim hover:text-cpr-yellow uppercase transition-colors"
                           href="{{ route('recover') }}">
                            {{ __('Forgot? Use your recovery email') }}
                        </a>
                        <p class="text-[10px] text-center text-cpr-text-dim uppercase">
                            No recovery email? Your gamemaster can tell you your username.
                        </p>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <a href="{{ route('how-to-use') }}"
                       class="text-[12px] text-cpr-red hover:text-cpr-yellow uppercase font-bold transition-colors border-b border-cpr-red/300 pb-1">
                        How to Use
                    </a>
                </div>
                {{-- Ko-fi support button, inside the box, below "How to Use". Only on the
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
