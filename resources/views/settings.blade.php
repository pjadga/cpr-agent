@extends('layouts.cyberpunk')

@section('content')
    <form action="{{ route('token.create') }}" method="POST" id="token-create-form" class="hidden">
        @csrf
    </form>
    <div class="max-w-4xl mx-auto p-8" x-data="{ onboardingStep: @js($isFirstTime ? 1 : 0), sessionInfo: @js($sessionInfo ?? null) }">
        <div class="flex justify-between flex-wrap items-center mb-8 border-b-2 border-cpr-border pb-4">
            <h2 class="text-4xl font-bold text-cpr-red uppercase tracking-tighter">
                Settings
            </h2>
            <div class="flex justify-between gap-2 pt-2 ml-auto">
                <a href="{{ route('dashboard') }}"
                   class="cyber-button bg-cpr-black-elevated border-cpr-border text-xs px-4 py-2 uppercase w-32 text-center">
                    Return
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="cyber-button bg-cpr-red-dark border-cpr-red-dark text-xs px-4 py-2 uppercase hover:bg-cpr-red w-32">
                        Disconnect
                    </button>
                </form>
            </div>
        </div>

        @if(session('success'))
            <div
                class="bg-cpr-teal-dark/50 border border-cpr-teal text-cpr-teal p-4 mb-8 uppercase text-xs tracking-widest">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div
                class="bg-cpr-red-dark/50 border border-cpr-red text-cpr-red p-4 mb-8 uppercase text-xs tracking-widest">
                {{ session('error') }}
            </div>
        @endif

        {{-- Why one of the three pieces (Reverb, Foundry URL, Bridge) is missing.
             This page still loads when they are, which is the point: it is where
             you come to fix them. --}}
        @if(\App\Support\ConnectionDiagnostics::has($connectionIssue ?? null))
            @php($diag = \App\Support\ConnectionDiagnostics::get($connectionIssue))
            <div class="bg-cpr-yellow-dark/20 border border-cpr-yellow p-4 mb-8">
                <div class="flex items-center gap-2 mb-2">
                    <i v-icon data-lucide="alert-triangle" class="w-4 h-4 text-cpr-yellow"></i>
                    <span class="text-cpr-yellow uppercase text-xs tracking-widest font-bold">{{ $diag['title'] }}</span>
                </div>
                <p class="text-cpr-text-dim text-xs leading-relaxed">{{ $diag['detail'] }}</p>
                <p class="text-cpr-text-main text-xs leading-relaxed mt-2"><strong>Fix:</strong> {{ $diag['hint'] }}</p>
            </div>
        @endif

        <!-- Session Status Banner -->
        <div x-data="{ sessionInfo: @js($sessionInfo ?? null), sessionLoading: {{ $sessionInfo ? 'false' : 'true' }}, loading: false, joinCode: '', joinMessage: '' }"
             x-init="
                 if (!sessionInfo) {
                     fetch('{{ route('session.info') }}')
                         .then(r => r.json())
                         .then(data => { sessionInfo = data.session; })
                         .catch(e => console.error('Failed to load session info', e))
                         .finally(() => { sessionLoading = false; });
                 }
             "
             class="mb-8">

            <!-- Loading session status -->
            <div x-show="sessionLoading"
                 class="bg-cpr-black-deep border-2 border-cpr-border p-6 rounded-lg flex items-center gap-3">
                <i class="fas fa-spinner fa-spin text-cpr-yellow"></i>
                <span class="text-cpr-text-dim text-sm uppercase tracking-widest">Loading session…</span>
            </div>

            <!-- GM Active Session -->
            <div x-show="sessionInfo && sessionInfo.type === 'gm'"
                 class="bg-cpr-teal-dark/20 border-2 border-cpr-teal p-6 rounded-lg shadow-[0_0_20px_rgba(0,255,159,0.1)]">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h3 class="text-cpr-teal font-bold uppercase tracking-widest text-sm mb-1">Active Session</h3>
                        <p class="text-2xl font-mono font-black text-white tracking-wider">{{ $sessionInfo['code'] ?? '---' }}</p>
                        <p class="text-xs text-cpr-text-dim mt-1">
                            {{ $sessionInfo['player_count'] ?? 0 }} player(s) connected
                            @if($sessionInfo['foundry_configured'] ?? false)
                                <span class="text-cpr-teal">• Foundry config synced</span>
                            @else
                                <span class="text-cpr-yellow">• Configure Foundry settings below to auto-configure players</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button @click="if(confirm('Terminate this session? All players will be disconnected.')) {
                            loading = true;
                            fetch('{{ route('session.delete') }}', { method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                                .then(r => r.json()).then(d => { sessionInfo = null; loading = false; alert(d.message); })
                        }"
                                class="cyber-button bg-cpr-red-dark border-cpr-red-dark px-4 py-2 uppercase text-xs font-bold hover:bg-cpr-red">
                            Terminate Session
                        </button>
                    </div>
                </div>
            </div>

            <!-- Player in Session -->
            <div x-show="sessionInfo && sessionInfo.type === 'player'"
                 class="bg-cpr-teal-dark/20 border-2 border-cpr-teal p-6 rounded-lg shadow-[0_0_20px_rgba(0,255,159,0.1)]">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h3 class="text-cpr-teal font-bold uppercase tracking-widest text-sm mb-1">Connected to Session</h3>
                        <p class="text-2xl font-mono font-black text-white tracking-wider">{{ $sessionInfo['code'] ?? '---' }}</p>
                        <p class="text-xs text-cpr-text-dim mt-1">
                            @if($sessionInfo['foundry_configured'] ?? false)
                                <span class="text-cpr-teal">✓ Auto-configured from GM session</span>
                            @else
                                <span class="text-cpr-yellow">⚠ GM has not synced Foundry config yet ,  manual setup may be needed</span>
                            @endif
                        </p>
                    </div>
                    <button @click="if(confirm('Leave this session?')) {
                        loading = true;
                        fetch('{{ route('session.leave') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                            .then(r => r.json()).then(d => { sessionInfo = null; loading = false; alert(d.message); location.reload(); })
                    }"
                            class="cyber-button bg-cpr-red-dark border-cpr-red-dark px-4 py-2 uppercase text-xs font-bold hover:bg-cpr-red">
                        Leave Session
                    </button>
                </div>
            </div>

            <!-- No Session (Player) - Join Flow -->
            <div x-show="!sessionLoading && !sessionInfo && {{ auth()->user()->is_gm ? 'false' : 'true' }}"
                 class="bg-cpr-black-deep border-2 border-cpr-border p-6 rounded-lg">
                <h3 class="text-cpr-yellow font-bold uppercase tracking-widest text-sm mb-4">Join a Session</h3>

                <!-- Primary: scan the GM's QR (or paste the link code) to auto-configure and link.
                     Isolated Vue mount ,  this Alpine page has no global #app root. Rendered once
                     per page: players get it here, GMs get it in their link block below. -->
                @unless(auth()->user()->is_gm)
                    <div id="qr-link-app">
                        <qr-link-scanner agent-link="{{ auth()->user()->agent_link }}"></qr-link-scanner>
                    </div>
                @endunless

                <div class="flex items-center gap-3 my-4">
                    <span class="flex-1 h-px bg-cpr-border"></span>
                    <span class="text-xs uppercase text-cpr-text-dim">or enter a code if connected</span>
                    <span class="flex-1 h-px bg-cpr-border"></span>
                </div>

                <div class="flex gap-2 items-end">
                    <div class="flex-1">
                        <label class="text-xs uppercase text-cpr-text-dim">Session Code</label>
                        <input type="text" x-model="joinCode"
                               placeholder="e.g. NEON-7X2"
                               class="cyber-input-field mt-1 w-full uppercase font-mono">
                    </div>
                    <button @click="
                        loading = true;
                        joinMessage = '';
                        fetch('{{ route('session.join') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' },
                            body: JSON.stringify({ code: joinCode.toUpperCase() })
                        })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                joinMessage = d.message || 'Joined successfully!';
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                joinMessage = d.error || 'Failed to join.';
                            }
                        })
                        .catch(e => { joinMessage = 'Network error.'; })
                        .finally(() => loading = false)
                        "
                        class="cyber-button bg-cpr-teal-dark border-cpr-teal text-black px-6 py-2 uppercase text-xs font-bold h-[42px]">
                        Join
                    </button>
                </div>
                <p x-show="joinMessage" x-text="joinMessage"
                   class="mt-3 text-xs" :class="joinMessage.includes('success') || joinMessage.includes('Already') ? 'text-cpr-teal' : 'text-cpr-red'">
                </p>
            </div>

            <!-- No Session (GM) - Create Flow -->
            <div x-show="!sessionLoading && !sessionInfo && {{ auth()->user()->is_gm ? 'true' : 'false' }}"
                 class="bg-cpr-black-deep border-2 border-cpr-border p-6 rounded-lg">
                <h3 class="text-cpr-yellow font-bold uppercase tracking-widest text-sm mb-4">Create a Session</h3>
                <p class="text-xs text-cpr-text-dim mb-4">
                    A session is created automatically when you save your Foundry connection below ,
                    or create one now. Players join using the session code.
                </p>
                <button @click="
                    loading = true;
                    fetch('{{ route('session.create') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            sessionInfo = d.session;
                            alert('Session created! Share this code with your players: ' + d.session.code);
                            // Reload so the browser subscribes to the GM's new session channel.
                            location.reload();
                        } else {
                            alert(d.error || 'Failed to create session.');
                            loading = false;
                        }
                    })
                    .catch(e => { alert('Network error.'); loading = false; })
                    "
                    class="cyber-button bg-cpr-teal-dark border-cpr-teal text-black px-8 py-2 uppercase text-xs font-bold">
                    Create Session
                </button>
            </div>
        </div>

        @if(auth()->user()->is_gm)
            <!-- GM self-link: same scan/paste flow players get. In Foundry the GM's
                 "Link my Agent" mints their own QR instantly (no round-trip). -->
            <details class="mb-8 group border border-cpr-border rounded-lg overflow-hidden bg-cpr-black-deep/40">
                <summary class="flex items-center justify-between p-4 cursor-pointer hover:bg-cpr-teal/10 transition-colors list-none">
                    <h3 class="text-cpr-yellow font-bold uppercase tracking-widest text-sm">
                        <i class="fas fa-qrcode mr-2"></i>Link this Agent to your Foundry user
                    </h3>
                    <div class="transition-transform duration-300 group-open:rotate-180">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-cpr-teal" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </summary>
                <div class="p-4 border-t border-cpr-border">
                    <p class="text-xs text-cpr-text-dim mb-4">
                        In Foundry, open <strong class="text-cpr-teal">Agent Bridge → Link my Agent</strong> ,
                        as the GM you get your own QR immediately. Scan it here (phone) or paste the
                        link code (desktop). Optional if the Bridge already shows your account as linked.
                    </p>
                    <div id="qr-link-app">
                        <qr-link-scanner agent-link="{{ auth()->user()->agent_link }}"></qr-link-scanner>
                    </div>
                </div>
            </details>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
            <!-- Global App Configuration -->
            <div class="space-y-6 relative">
                <h3 class="text-xl font-bold text-cpr-yellow uppercase tracking-widest border-b border-cpr-yellow/30 pb-2">
                    <span class="text-cpr-teal">Step 1</span> · Connect to Foundry
                </h3>
                <p class="text-xs text-cpr-text-dim">
                    @if(auth()->user()->is_gm)
                        Enter your Foundry URL and generate an API key ,  saving creates your game session
                        automatically. Then use the <strong class="text-cpr-teal">Setup Code</strong> below in
                        Foundry → Agent Bridge → Quick Setup, and share the session code with your players.
                    @else
                        Scan your GM's QR (or paste their link code) up top to auto-fill all of this ,
                        or join with a session code.
                    @endif
                </p>
                <form action="{{ route('config.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <!-- Foundry URL -->
                    <div>
                        <div class="flex items-center gap-2">
                            <x-input-label for="foundry_url" :value="__('Foundry URL')"
                                           class="text-xs uppercase text-cpr-teal"/>
                            <div class="group relative flex items-center">
                                <i data-lucide="help-circle"
                                   class="w-3 h-3 text-cpr-text-dim hover:text-cpr-teal cursor-help transition-colors"></i>
                                <div
                                    class="absolute left-full ml-2 w-48 p-2 bg-cpr-black-deep border border-cpr-teal text-[10px] text-cpr-text-main uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                    The full URL of your Foundry VTT instance.
                                </div>
                            </div>
                        </div>
                        <x-text-input type="text" name="foundry_url" id="foundry_url"
                                      value="{{ old('foundry_url', $config->foundry_url) }}"
                                      class="cyber-input-field mt-1 w-full"/>
                    </div>

                    <!-- API Key -->
                    <div>
                        <div class="flex items-center gap-2">
                            <x-input-label readonly for="api_key" :value="__('API Key')"
                                           class="text-xs uppercase text-cpr-teal"/>
                            @if(auth()->user()->is_gm)
                                <span
                                    class="ml-auto bg-cpr-red/20 border border-cpr-red text-cpr-red px-2 py-0.5 text-[10px] font-black uppercase tracking-widest animate-pulse">
                                GM Authorized
                            </span>
                            @endif
                            <div class="group relative flex items-center">
                                <i data-lucide="help-circle"
                                   class="w-3 h-3 text-cpr-text-dim hover:text-cpr-teal cursor-help transition-colors"></i>
                                <div
                                    class="absolute left-full ml-2 w-48 p-2 bg-cpr-black-deep border border-cpr-teal text-[10px] text-cpr-text-main uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                    Must be the same key added to the Cyberpunk Agent Bridge module
                                </div>
                            </div>
                        </div>
                        <x-text-input type="text" name="api_key" id="api_key"
                                      value="{{ session('flash_token', old('api_key', $config->api_key)) }}"
                                      class="cyber-input-field mt-1 w-full"/>
                    </div>

                    <div>
                        @if ($config->api_key)
                            <details
                                class="group border border-cpr-teal/30 rounded-lg overflow-hidden bg-cpr-black-deep/40" {{ session('flash_token') ? 'open' : '' }}>
                                <summary
                                    class="flex items-center justify-between p-4 cursor-pointer hover:bg-cpr-teal/10 transition-colors list-none">
                                    <h3 class="font-bold text-cpr-teal">API Key Generation</h3>
                                    <div class="transition-transform duration-300 group-open:rotate-180">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-cpr-teal"
                                             fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </div>
                                </summary>
                                <div class="p-4 border-t border-cpr-teal/20">
                                    @else
                                        <h3 class="font-bold mb-4">API Key Generation</h3>
                                    @endif

                                    @if (session('flash_token'))
                                        <div class="bg-green-800 p-4 mb-4 rounded">
                                            <p class="font-mono text-sm break-all select-all">
                                                {{ session('flash_token') }}
                                            </p>
                                            <p class="text-xs mt-2 text-green-300">
                                                ⚠️ Copy this now. You won't see it again.
                                            </p>
                                        </div>
                                    @endif

                                    @if (session('flash_token_replaced'))
                                        <div class="border border-amber-500 bg-amber-950/60 p-4 mb-4 rounded">
                                            <p class="text-xs text-amber-200 uppercase tracking-tight font-bold mb-1">
                                                Your old key no longer works
                                            </p>
                                            <p class="text-xs text-amber-100/90">
                                                Paste the key above into <span class="font-mono">Configure Settings →
                                                Agent Bridge → API Key</span> in Foundry. Until you do, the Bridge will
                                                report 401 errors and show as offline.
                                            </p>
                                        </div>
                                    @endif

                                    <button type="submit" form="token-create-form"
                                            @if ($config->api_key)
                                                onclick="return confirm('This replaces your current API key. The key already saved in your Foundry Bridge module will stop working until you paste the new one in. Continue?')"
                                            @endif
                                            class="cyber-button bg-cpr-teal-dark border-cpr-teal text-black w-full uppercase font-bold py-2">
                                        {{ $config->api_key ? 'Replace Connection Key' : 'Generate Connection Key' }}
                                    </button>

                                    @if ($config->api_key)
                                </div>
                            </details>
                        @endif
                    </div>

                    <!-- Channel ID -->
                    <div class="relative">
                        <div class="flex items-center gap-2">
                            <x-input-label for="channel_id" :value="__('Channel ID')"
                                           class="text-xs uppercase text-cpr-teal"/>
                            <div class="group relative flex items-center">
                                <i data-lucide="help-circle"
                                   class="w-3 h-3 text-cpr-text-dim hover:text-cpr-teal cursor-help transition-colors"></i>
                                <div
                                    class="absolute left-full ml-2 w-64 p-2 bg-cpr-black-deep border border-cpr-teal text-[10px] text-cpr-text-main uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                    The shared broadcast channel.
                                    @if(auth()->user()->is_gm)
                                        Derived from your session automatically ,  share it with your players.
                                    @else
                                        Auto-filled when you link or join a session above.
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if(auth()->user()->is_gm)
                            <x-text-input type="text" name="channel_id" id="channel_id"
                                          value="{{ old('channel_id', $user->channel_id) }}"
                                          class="cyber-input-field mt-1 w-full opacity-70" readonly/>
                        @else
                            <x-text-input type="text" name="channel_id" id="channel_id"
                                          value="{{ old('channel_id', $user->channel_id) }}"
                                          class="cyber-input-field mt-1 w-full"/>
                        @endif
                        <x-input-error :messages="$errors->get('channel_id')"
                                       class="mt-1 text-cpr-red text-[10px] uppercase"/>

                        @if(auth()->user()->is_gm)
                            <!-- One-paste Foundry setup for the Bridge's Quick Setup menu. Emits a
                                 short one-time code (typeable across devices ,  phone Agent, desktop
                                 Foundry) plus the full base64 blob as a same-device fallback. -->
                            <div class="mt-3" x-data="{ setupCode: '', shortCode: '', loading: false, copied: false, error: '' }">
                                <button type="button"
                                        :disabled="loading"
                                        @click="
                                            loading = true; error = ''; copied = false;
                                            fetch('{{ route('settings.foundry-setup-code') }}', { headers: { 'Accept': 'application/json' } })
                                                .then(async r => { const d = await r.json(); if (!r.ok) throw new Error(d.message || d.error || 'Failed'); return d; })
                                                .then(async d => {
                                                    setupCode = d.code;
                                                    shortCode = d.short_code || '';
                                                    try { await navigator.clipboard.writeText(d.code); copied = true; } catch (e) { /* clipboard blocked; copy from the box */ }
                                                })
                                                .catch(e => { error = e.message; })
                                                .finally(() => loading = false)
                                        "p
                                        class="cyber-button bg-cpr-black-elevated border-cpr-teal text-cpr-teal px-3 py-1.5 uppercase text-[10px] font-bold w-full">
                                    <i data-lucide="clipboard-copy" class="w-3 h-3 inline -mt-0.5"></i>
                                    <span x-show="!loading && !shortCode">Get Foundry Setup Code</span>
                                    <span x-show="!loading && shortCode">Get a fresh code</span>
                                    <span x-show="loading">…</span>
                                </button>
                                <p class="text-[10px] text-cpr-text-dim mt-1">
                                    One code configures the Agent Bridge module (Channel ID, API key, Reverb key). Make sure the Bridge already has the URL of your Agent if self-hosting.
                                </p>
                                <p x-show="error" x-text="error" class="text-cpr-red text-[10px] mt-1"></p>

                                <div x-show="shortCode"
                                     class="mt-2 text-center border border-cpr-teal/40 rounded-lg p-3 bg-cpr-black-deep">
                                    <p class="text-[10px] uppercase tracking-widest text-cpr-text-dim">Ok Gr
                                        In Foundry: Agent Bridge → Quick Setup → type this code
                                    </p>
                                    <p class="text-3xl font-mono font-black text-cpr-teal tracking-widest my-1 select-all"
                                       x-text="shortCode"></p>
                                    <p class="text-[10px] text-cpr-text-dim">One-time use · expires in 5 minutes</p>
                                </div>

                                <details x-show="setupCode" class="mt-2">
                                    <summary class="text-[10px] uppercase text-cpr-text-dim cursor-pointer">
                                        Same device? Paste the full code instead <span x-show="copied">(✓ copied to clipboard)</span>
                                    </summary>
                                    <textarea x-model="setupCode" readonly rows="2"
                                              @focus="$el.select()"
                                              class="cyber-input-field mt-2 w-full font-mono text-[10px] break-all select-all"></textarea>
                                    <p class="text-cpr-yellow text-[10px] mt-1">
                                        ⚠ Contains your API key ,  share only with your own Foundry world.
                                    </p>
                                </details>
                            </div>
                        @endif

                        <!-- Step 2 Tooltip -->
                        <div x-show="onboardingStep === 2"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 translate-y-4"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute z-50 top-full mt-4 left-0 w-full bg-cpr-black border-2 border-cpr-teal shadow-[0_0_20px_rgba(0,255,159,0.3)] p-6 hidden-none">
                            <div
                                class="absolute -top-3 left-8 w-6 h-6 bg-cpr-black border-l-2 border-t-2 border-cpr-teal transform rotate-45"></div>

                            <div class="flex items-center justify-between border-b border-cpr-teal/30 mb-4 pb-2">
                                <h2 class="text-xl font-bold text-cpr-teal uppercase tracking-tighter">
                                    System Initialization <span
                                        class="text-xs font-mono ml-2 opacity-50">[STEP 02]</span>
                                </h2>
                                <div class="flex gap-1">
                                    <div class="bg-cpr-teal w-3 h-1"></div>
                                    <div class="bg-cpr-teal w-3 h-1"></div>
                                    <div class="bg-cpr-teal/20 w-3 h-1"></div>
                                </div>
                            </div>

                            <p class="text-cpr-text mb-4 leading-relaxed text-sm">
                                @if(auth()->user()->is_gm)
                                    Click <strong>Get Foundry Setup Code</strong> and type the short code into
                                    Foundry → Agent Bridge → Quick Setup. That configures the whole Foundry side.
                                    Share your session code with players.
                                @else
                                    Your Channel ID fills in automatically when you link or join a session ,
                                    only change it if your GM tells you to.
                                @endif
                            </p>
                            <div
                                class="bg-cpr-teal/10 border-l-2 border-cpr-teal p-3 mb-6 italic text-xs text-cpr-text">
                                Uplink established. Session handshake in progress...
                            </div>

                            <div class="flex justify-between items-center">
                                <button type="button" @click="onboardingStep = 1"
                                        class="cyber-button bg-cpr-black-elevated border-cpr-border px-6 py-2 uppercase text-xs text-cpr-text">
                                    Previous
                                </button>
                                <button type="button" @click="onboardingStep = 3"
                                        class="cyber-button bg-cpr-teal-dark border-cpr-teal px-8 py-2 uppercase text-xs font-bold text-black">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit"
                            class="cyber-button bg-cpr-teal-dark border-cpr-teal text-black w-full uppercase font-bold py-2">
                        Save Connection
                    </button>
                </form>

                <!-- Players auto-configure from these settings on join ,  synced automatically
                     on session create and whenever you save. Shown until a Foundry URL exists. -->
                <div x-show="sessionInfo && sessionInfo.type === 'gm' && !sessionInfo.foundry_configured"
                     class="mt-6 pt-6 border-t border-cpr-border">
                    <p class="text-xs text-cpr-yellow">
                        ⚠ Set your <strong>Foundry URL</strong> above and save ,  your players will then
                        auto-configure when they join. No extra step needed.
                    </p>
                </div>

                <!-- Step 1 Tooltip -->
                <div x-show="onboardingStep === 1"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="absolute z-50 top-full mt-4 left-0 w-full bg-cpr-black border-2 border-cpr-teal shadow-[0_0_20px_rgba(0,255,159,0.3)] p-6 hidden-none">
                    <div
                        class="absolute -top-3 left-8 w-6 h-6 bg-cpr-black border-l-2 border-t-2 border-cpr-teal transform rotate-45"></div>

                    <div class="flex items-center justify-between border-b border-cpr-teal/30 mb-4 pb-2">
                        <h2 class="text-xl font-bold text-cpr-teal uppercase tracking-tighter">
                            System Initialization <span class="text-xs font-mono ml-2 opacity-50">[STEP 01]</span>
                        </h2>
                        <div class="flex gap-1">
                            <div class="bg-cpr-teal w-3 h-1"></div>
                            <div class="bg-cpr-teal/20 w-3 h-1"></div>
                            <div class="bg-cpr-teal/20 w-3 h-1"></div>
                        </div>
                    </div>

                    <p class="text-cpr-text mb-4 leading-relaxed text-sm">
                        @if(auth()->user()->is_gm)
                            Enter your Foundry URL and generate an API key. Saving your connection also
                            creates your game session automatically ,  no separate step.
                        @else
                            You don't need to type any of this ,  ask your GM for a QR code or link code and
                            use <strong>Scan to Link</strong> up top. Everything configures itself.
                        @endif
                    </p>
                    <div class="bg-cpr-teal/10 border-l-2 border-cpr-teal p-3 mb-6 italic text-xs text-cpr-text">
                        Welcome, Edgerunner. Before we hit the Net, we need to establish a stable uplink to your Foundry
                        instance.
                    </div>

                    <div class="flex justify-end">
                        <button type="button" @click="onboardingStep = 2"
                                class="cyber-button bg-cpr-teal-dark border-cpr-teal px-8 py-2 uppercase text-xs font-bold text-black">
                            Next
                        </button>
                    </div>
                </div>
            </div>

            <!-- User Profile Synchronization -->
            <div class="space-y-6">
                <div class="flex justify-between items-center border-b border-cpr-yellow/30 pb-2">
                    <h3 class="text-xl font-bold text-cpr-yellow uppercase tracking-widest">
                        <span class="text-cpr-teal">Step 2</span> · Your Agent
                    </h3>
                    <div class="flex gap-2">
                        <a href="{{ route('settings.refresh') }}"
                           class="text-cpr-yellow hover:text-cpr-yellow transition-colors" title="Refresh Sync">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <p class="text-xs text-cpr-text-dim">
                    Once connected, pick the character you'll play and choose your interface theme.
                </p>
                {{-- Was `&& !config('services.foundry.base_url')`, which pointed at a
                     retired relay whose default was a non-empty string, so this
                     guard never once rendered: an account with no Foundry saved
                     was shown the actor picker instead of being told to do Step 1. --}}
                @if(!$config->foundry_url)
                    <div class="bg-cpr-red-dark/30 border border-cpr-red-dark text-cpr-red p-4 text-xs italic">
                        SYSTEM OFFLINE: Finish <strong>Step 1 · Connect to Foundry</strong> to enable Agent sync.
                    </div>
                @else
                    <form method="POST" action="{{ route('profile-setup.store') }}" class="space-y-4">
                        @csrf
                        <!-- Default Actor ID -->
                        <div class="relative">
                            <div class="flex items-center gap-2">
                                <x-input-label for="actor_selection" :value="__('Primary Biometric Signature (Actor)')"
                                               class="text-xs uppercase text-cpr-muted"/>
                                <div class="group relative flex items-center">
                                    <i data-lucide="help-circle"
                                       class="w-3 h-3 text-cpr-text-dim hover:text-cpr-yellow cursor-help transition-colors"></i>
                                    <div
                                        class="absolute left-full ml-2 w-48 p-2 bg-cpr-black-deep border border-cpr-yellow text-[10px] text-cpr-text-main uppercase tracking-tighter hidden group-hover:block z-50 shadow-2xl">
                                        Loads playable characters and player-owned characters from the current session.
                                    </div>
                                </div>
                            </div>
                            <select id="actor_selection" class="cyber-select-field mt-1 text-base">
                                <option value="" disabled {{ !$user->default_actor_id ? 'selected' : '' }}>Select
                                    Biometric Data
                                </option>
                                @foreach($actors as $actor)
                                    <option value="{{ $actor['actorId'] }}|{{ $actor['tokenId'] }}"
                                            {{ ($user->default_actor_id == $actor["actorId"]) ? 'selected' : '' }} class="cyber-select-option">
                                        {{ $actor["name"] }} [{{ $actor["actorId"] }}]
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="default_actor_id" id="default_actor_id"
                                   value="{{ $user->default_actor_id }}">
                            <input type="hidden" name="default_token_id" id="default_token_id"
                                   value="{{ $user->default_token_id }}">
                            <x-input-error :messages="$errors->get('default_actor_id')"
                                           class="mt-1 text-cpr-red text-[10px] uppercase"/>

                            <!-- Step 3 Tooltip -->
                            <div x-show="onboardingStep === 3"
                                 x-transition:enter="transition ease-out duration-300"
                                 x-transition:enter-start="opacity-0 translate-y-4"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="absolute z-50 top-full mt-4 left-0 w-full bg-cpr-black border-2 border-cpr-teal shadow-[0_0_20px_rgba(0,255,159,0.3)] p-6 hidden-none">
                                <div
                                    class="absolute -top-3 left-8 w-6 h-6 bg-cpr-black border-l-2 border-t-2 border-cpr-teal transform rotate-45"></div>

                                <div class="flex items-center justify-between border-b border-cpr-teal/30 mb-4 pb-2">
                                    <h2 class="text-xl font-bold text-cpr-teal uppercase tracking-tighter">
                                        System Initialization <span
                                            class="text-xs font-mono ml-2 opacity-50">[STEP 03]</span>
                                    </h2>
                                    <div class="flex gap-1">
                                        <div class="bg-cpr-teal w-3 h-1"></div>
                                        <div class="bg-cpr-teal w-3 h-1"></div>
                                        <div class="bg-cpr-teal w-3 h-1"></div>
                                    </div>
                                </div>

                                <p class="text-cpr-text mb-4 leading-relaxed text-sm">
                                    @if(auth()->user()->is_gm)
                                        Assign characters to your players in Foundry (Agent Bridge →
                                        Manage Characters), then select and save your own character here.
                                    @else
                                        Once your GM assigns you a character, select and save it here.
                                    @endif
                                    @if($sessionInfo && $sessionInfo['foundry_configured'])
                                        Your session is auto-configured ,  just pick your character!
                                    @endif
                                </p>
                                <div
                                    class="bg-cpr-teal/10 border-l-2 border-cpr-teal p-3 mb-6 italic text-xs text-cpr-text">
                                    Finalizing biometric handshake.
                                </div>

                                <div class="flex justify-between items-center">
                                    <button type="button" @click="onboardingStep = 2"
                                            class="cyber-button bg-cpr-black-elevated border-cpr-border px-6 py-2 uppercase text-xs text-cpr-text">
                                        Previous
                                    </button>
                                    <button type="button"
                                            @click="fetch('{{ route('settings.onboarding.read') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' } }).then(() => onboardingStep = 0)"
                                            class="cyber-button bg-cpr-teal-dark border-cpr-teal px-8 py-2 uppercase text-xs font-bold text-black">
                                        Begin Sync
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Interface Theme (personal preference) -->
                        <div>
                            <x-input-label for="theme" :value="__('Interface Theme')"
                                           class="text-xs uppercase text-cpr-muted"/>
                            <select id="theme" name="theme" class="cyber-select-field mt-1 text-base">
                                <option value="night"
                                        {{ old('theme', $config->theme) == 'night' ? 'selected' : '' }} class="cyber-select-option">
                                    Night Mode
                                </option>
                                <option value="day"
                                        {{ old('theme', $config->theme) == 'day' ? 'selected' : '' }} class="cyber-select-option">
                                    Day Mode
                                </option>
                                <option value="bartmoss"
                                        {{ old('theme', $config->theme) == 'bartmoss' ? 'selected' : '' }} class="cyber-select-option">
                                    Rache Bartmoss
                                </option>
                                <option value="corpo"
                                        {{ old('theme', $config->theme) == 'corpo' ? 'selected' : '' }} class="cyber-select-option">
                                    Corpo
                                </option>
                                <option value="medtech"
                                        {{ old('theme', $config->theme) == 'medtech' ? 'selected' : '' }} class="cyber-select-option">
                                    Med Tech
                                </option>
                                <option value="blind"
                                        {{ old('theme', $config->theme) == 'blind' ? 'selected' : '' }} class="cyber-select-option">
                                    Colour Blind Mode
                                </option>
                            </select>
                        </div>


                        <button type="submit"
                                class="cyber-button bg-cpr-teal-dark border-cpr-teal w-full uppercase font-bold py-2 text-black">
                            Save Agent
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- UI Settings (client-side preferences, stored in localStorage) -->
        <div class="mt-12 pt-8 border-t-2 border-cpr-border">
            <h3 class="text-2xl font-bold text-cpr-yellow uppercase tracking-tighter mb-8">
                Interface Settings
            </h3>

            <div class="bg-cpr-black-deep border-2 border-cpr-border p-6 rounded-lg space-y-6"
                 x-data="{
                     reduceMotion: false,
                     shadowAnimation: false,
                     disableTapHold: false,
                     hideBackground: false,
                     savedMotion: false,
                     savedShadowAnim: false,
                     savedTapHold: false,
                     savedBackground: false,
                     init() {
                         this.reduceMotion = localStorage.getItem('cpr-reduce-motion') === '1';
                         this.shadowAnimation = localStorage.getItem('cpr-shadow-animation') === '1';
                         this.disableTapHold = localStorage.getItem('cpr-disable-tap-hold') === '1';
                         this.hideBackground = localStorage.getItem('cpr-hide-background') === '1';
                     },
                     flash(key) {
                         this[key] = true;
                         clearTimeout(this['_t_' + key]);
                         this['_t_' + key] = setTimeout(() => { this[key] = false; }, 2500);
                     },
                     setReduceMotion(v) {
                         this.reduceMotion = v;
                         localStorage.setItem('cpr-reduce-motion', v ? '1' : '0');
                         document.documentElement.classList.toggle('cpr-reduce-motion', v);
                         this.flash('savedMotion');
                     },
                     setShadowAnimation(v) {
                         this.shadowAnimation = v;
                         localStorage.setItem('cpr-shadow-animation', v ? '1' : '0');
                         this.flash('savedShadowAnim');
                     },
                     setDisableTapHold(v) {
                         this.disableTapHold = v;
                         localStorage.setItem('cpr-disable-tap-hold', v ? '1' : '0');
                         this.flash('savedTapHold');
                     },
                     setHideBackground(v) {
                         this.hideBackground = v;
                         localStorage.setItem('cpr-hide-background', v ? '1' : '0');
                         document.documentElement.classList.toggle('cpr-hide-background', v);
                         this.flash('savedBackground');
                     },
                 }">

                <!-- Reduce Motion -->
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-bold text-cpr-teal uppercase tracking-widest">Reduce Shadows</p>
                        <p class="text-xs text-cpr-text-dim mt-1">
                            Removes the shadow depth effect.
                        </p>
                        <p x-show="savedMotion" x-transition.opacity x-cloak
                           class="text-[10px] text-cpr-teal uppercase tracking-widest mt-1">
                            ✓ Saved
                        </p>
                    </div>
                    <button type="button" role="switch" :aria-checked="reduceMotion"
                            @click="setReduceMotion(!reduceMotion)"
                            class="relative shrink-0 w-14 h-7 border-2 transition-colors"
                            :class="reduceMotion ? 'bg-cpr-teal-dark border-cpr-teal' : 'bg-cpr-black-elevated border-cpr-border'">
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 transition-transform"
                              :class="reduceMotion ? 'translate-x-7 bg-cpr-teal' : 'translate-x-0 bg-cpr-text-dim'"></span>
                    </button>
                </div>

                <!-- Shadow Animation -->
                <div class="flex items-center justify-between gap-4 pt-6 border-t border-cpr-border/50">
                    <div>
                        <p class="text-sm font-bold text-cpr-teal uppercase tracking-widest">Shadow Animation</p>
                        <p class="text-xs text-cpr-text-dim mt-1">
                            Drives the frame shadow depth from device tilt (gyroscope). Off by default; the static shadows still show.
                        </p>
                        <p x-show="savedShadowAnim" x-transition.opacity x-cloak
                           class="text-[10px] text-cpr-teal uppercase tracking-widest mt-1">
                            ✓ Saved
                        </p>
                    </div>
                    <button type="button" role="switch" :aria-checked="shadowAnimation"
                            @click="setShadowAnimation(!shadowAnimation)"
                            class="relative shrink-0 w-14 h-7 border-2 transition-colors"
                            :class="shadowAnimation ? 'bg-cpr-teal-dark border-cpr-teal' : 'bg-cpr-black-elevated border-cpr-border'">
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 transition-transform"
                              :class="shadowAnimation ? 'translate-x-7 bg-cpr-teal' : 'translate-x-0 bg-cpr-text-dim'"></span>
                    </button>
                </div>

                <!-- Disable Tap and Hold -->
                <div class="flex items-center justify-between gap-4 pt-6 border-t border-cpr-border/50">
                    <div>
                        <p class="text-sm font-bold text-cpr-teal uppercase tracking-widest">Disable Tap and Hold</p>
                        <p class="text-xs text-cpr-text-dim mt-1">
                            Disables the tap-and-hold (press-and-hold) radial menu.
                        </p>
                        <p x-show="savedTapHold" x-transition.opacity x-cloak
                           class="text-[10px] text-cpr-teal uppercase tracking-widest mt-1">
                            ✓ Saved
                        </p>
                    </div>
                    <button type="button" role="switch" :aria-checked="disableTapHold"
                            @click="setDisableTapHold(!disableTapHold)"
                            class="relative shrink-0 w-14 h-7 border-2 transition-colors"
                            :class="disableTapHold ? 'bg-cpr-teal-dark border-cpr-teal' : 'bg-cpr-black-elevated border-cpr-border'">
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 transition-transform"
                              :class="disableTapHold ? 'translate-x-7 bg-cpr-teal' : 'translate-x-0 bg-cpr-text-dim'"></span>
                    </button>
                </div>

                <!-- Hide Agent Background -->
                <div class="flex items-center justify-between gap-4 pt-6 border-t border-cpr-border/50">
                    <div>
                        <p class="text-sm font-bold text-cpr-teal uppercase tracking-widest">Hide Agent Background</p>
                        <p class="text-xs text-cpr-text-dim mt-1">
                            Hides the blue-tinted background graphic on the Agent interface.
                        </p>
                        <p x-show="savedBackground" x-transition.opacity x-cloak
                           class="text-[10px] text-cpr-teal uppercase tracking-widest mt-1">
                            ✓ Saved
                        </p>
                    </div>
                    <button type="button" role="switch" :aria-checked="hideBackground"
                            @click="setHideBackground(!hideBackground)"
                            class="relative shrink-0 w-14 h-7 border-2 transition-colors"
                            :class="hideBackground ? 'bg-cpr-teal-dark border-cpr-teal' : 'bg-cpr-black-elevated border-cpr-border'">
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 transition-transform"
                              :class="hideBackground ? 'translate-x-7 bg-cpr-teal' : 'translate-x-0 bg-cpr-text-dim'"></span>
                    </button>
                </div>

                <p class="text-[10px] text-cpr-text-dim uppercase tracking-widest pt-2">
                    These preferences are stored on this device and apply the next time the Agent loads.
                </p>
            </div>
        </div>

        <!-- Account Management -->
        <div class="mt-12 pt-8 border-t-2 border-cpr-border">
            <h3 class="text-2xl font-bold text-cpr-yellow uppercase tracking-tighter mb-8">
                Account Control & Security
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <!-- Profile Info -->
                <div class="space-y-6">
                    <h4 class="text-lg font-bold text-cpr-text-muted uppercase tracking-widest border-b border-cpr-border pb-2">
                        Profile Data
                    </h4>
                    <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
                        @csrf
                        @method('patch')

                        <div>
                            <x-input-label for="name" :value="__('User Handle')"
                                           class="text-xs uppercase text-cpr-teal"/>
                            <x-text-input id="name" name="name" type="text" class="cyber-input-field mt-1 w-full"
                                          :value="old('name', $user->name)" required autocomplete="name"/>
                            <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase"
                                           :messages="$errors->get('name')"/>
                        </div>

                        <div>
                            <x-input-label for="email" :value="__('Agent ID (Email)')"
                                           class="text-xs uppercase text-cpr-teal"/>
                            <x-text-input id="email" name="email" type="email" class="cyber-input-field mt-1 w-full"
                                          :value="old('email', $user->email)" required autocomplete="username"/>
                            <x-input-error class="mt-1 text-cpr-red text-[10px] uppercase"
                                           :messages="$errors->get('email')"/>
                        </div>

                        <button type="submit"
                                class="cyber-button bg-cpr-teal-dark border-cpr-teal-dark w-full uppercase font-bold py-2 hover:bg-cpr-teal text-black">
                            Update Profile
                        </button>
                    </form>

                    <!-- Declared role ,  drives the whole onboarding flow. Foundry
                         verifies it once the Bridge connects. -->
                    <form method="post" action="{{ route('settings.role') }}" class="pt-4 border-t border-cpr-border/50">
                        @csrf
                        @method('patch')

                        <x-input-label for="role" :value="__('Role')" class="text-xs uppercase text-cpr-teal"/>
                        <div class="flex gap-2 items-stretch mt-1">
                            <select id="role" name="role" class="cyber-select-field flex-1 text-base">
                                <option value="player" {{ !$user->is_gm ? 'selected' : '' }} class="cyber-select-option">
                                    Player
                                </option>
                                <option value="gm" {{ $user->is_gm ? 'selected' : '' }} class="cyber-select-option">
                                    Game Master
                                </option>
                            </select>
                            <button type="submit"
                                    class="cyber-button bg-cpr-teal-dark border-cpr-teal-dark px-6 uppercase font-bold text-xs hover:bg-cpr-teal text-black">
                                Update Role
                            </button>
                        </div>
                        <p class="text-[10px] text-cpr-text-dim mt-1 uppercase">
                            Switching role changes your setup flow. GMs must terminate their active session
                            before switching to Player.
                        </p>
                    </form>
                </div>

                <!-- Security/Password -->
                <div class="space-y-6">
                    <h4 class="text-lg font-bold text-cpr-text-muted uppercase tracking-widest border-b border-cpr-border pb-2">
                        Change Password
                    </h4>
                    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
                        @csrf
                        @method('put')

                        <div>
                            <x-input-label for="update_password_current_password" :value="__('Current Password')"
                                           class="text-xs uppercase text-cpr-teal"/>
                            <x-text-input id="update_password_current_password" name="current_password" type="password"
                                          class="cyber-input-field mt-1 w-full" autocomplete="current-password"/>
                            <x-input-error :messages="$errors->updatePassword->get('current_password')"
                                           class="mt-1 text-cpr-red text-[10px] uppercase"/>
                        </div>

                        <div>
                            <x-input-label for="update_password_password" :value="__('New Password')"
                                           class="text-xs uppercase text-cpr-teal"/>
                            <x-text-input id="update_password_password" name="password" type="password"
                                          class="cyber-input-field mt-1 w-full" autocomplete="new-password"/>
                            <x-input-error :messages="$errors->updatePassword->get('password')"
                                           class="mt-1 text-cpr-red text-[10px] uppercase"/>
                        </div>

                        <div>
                            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')"
                                           class="text-xs uppercase text-cpr-teal"/>
                            <x-text-input id="update_password_password_confirmation" name="password_confirmation"
                                          type="password" class="cyber-input-field mt-1 w-full"
                                          autocomplete="new-password"/>
                            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')"
                                           class="mt-1 text-cpr-red text-[10px] uppercase"/>
                        </div>

                        <button type="submit"
                                class="cyber-button bg-cpr-teal-dark border-cpr-teal-dark w-full uppercase font-bold py-2 hover:bg-cpr-teal text-black">
                            Update
                        </button>
                    </form>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="mt-12 pt-8 border-t border-cpr-red-dark/30">
                <div
                    class="flex flex-col md:flex-row items-center justify-between gap-6 bg-cpr-red-dark/10 p-6 border border-cpr-red-dark/20">
                    <div>
                        <h4 class="text-lg font-bold text-cpr-red uppercase tracking-widest">Terminate Account</h4>
                        <p class="text-xs text-cpr-red/60 uppercase mt-1">Warning: This action is permanent and will
                            wipe all Agent sync data.</p>
                    </div>

                    <button
                        x-data=""
                        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
                        class="cyber-button bg-cpr-red-dark border-cpr-red-dark px-8 py-2 uppercase font-bold hover:bg-cpr-red whitespace-nowrap"
                    >
                        Delete Account
                    </button>
                </div>
            </div>
        </div>

        <!-- Help -->
        <div class="mt-12 pt-8 border-t-2 border-cpr-border text-center">
            <a href="{{ route('how-to-use') }}"
               class="cyber-button bg-cpr-black-elevated border-cpr-border text-sm px-8 py-3 uppercase font-bold inline-flex items-center gap-2">
                <i data-lucide="help-circle" class="w-4 h-4"></i>
                How to Use
            </a>
        </div>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}"
              class="p-6 bg-cpr-black-deep border-2 border-cpr-red">
            @csrf
            @method('delete')

            <h2 class="text-2xl font-bold text-cpr-red uppercase tracking-tighter">
                Confirm Account Termination
            </h2>

            <p class="mt-4 text-sm text-cpr-text-muted uppercase tracking-widest">
                Once your account is deleted, all of its resources and data will be permanently deleted. Please enter
                your password to confirm you would like to permanently delete your account.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only"/>

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="cyber-input-field mt-1 block w-3/4"
                    placeholder="{{ __('Enter Encryption Key') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')"
                               class="mt-2 text-cpr-red text-[10px] uppercase"/>
            </div>

            <div class="mt-6 flex justify-end gap-4">
                <button type="button" x-on:click="$dispatch('close')"
                        class="cyber-button bg-cpr-black-elevated border-cpr-border px-6 py-2 uppercase text-xs">
                    Abort
                </button>

                <button type="submit"
                        class="cyber-button bg-cpr-red-dark border-cpr-red-dark px-6 py-2 uppercase text-xs hover:bg-cpr-red">
                    Terminate Account
                </button>
            </div>
        </form>
    </x-modal>

    <script @cspNonce>
        const actorSelection = document.getElementById('actor_selection');
        if (actorSelection) {
            const syncIds = () => {
                const val = actorSelection.value;
                if (val && val.includes('|')) {
                    let [actorId, tokenId] = val.split('|');
                    if (actorId.startsWith('Actor.')) {
                        actorId = actorId.replace('Actor.', '');
                    }
                    document.getElementById('default_actor_id').value = actorId;
                    document.getElementById('default_token_id').value = tokenId !== 'null' ? tokenId : '';
                }
            };

            actorSelection.addEventListener('change', syncIds);

            // Initialize on load
            syncIds();
        }
    </script>
    </div>
@endsection
