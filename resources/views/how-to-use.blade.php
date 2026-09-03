@extends('layouts.cyberpunk')

@section('content')
    <div
        class="min-h-screen flex flex-col items-center justify-center  bg-[url('{{ asset('images/external/splash-square.png') }}')] bg-cover bg-center bg-no-repeat bg-blend-overlay bg-cpr-black/80">
        <div
            class="cpr-panel p-4 max-w-4xl w-full h-full border-t-4 border-t-red-600 shadow-2xl backdrop-blur-sm overflow-y-auto">
            <div class="mb-8 border-b border-cpr-red/30 pb-4">
                <h1 class="text-4xl font-black glow-red text-cpr-red uppercase tracking-tighter">How to Use</h1>
                <p class="text-cpr-text-muted italic"><br>Follow these steps to synchronize your Agent interface with
                    Night City.</p>
            </div>

            {{-- Ko-fi support button — top of the page, under the title. Colour matches the
                 dark panel. Shown on the How to Use page for every instance. --}}
            <div class="mb-8 flex flex-col items-center gap-2 text-center">
                <div class="flex justify-center">
                    <script type='text/javascript' src='https://storage.ko-fi.com/cdn/widget/Widget_2.js' @cspNonce></script>
                    <script type='text/javascript' @cspNonce>
                        kofiwidget2.init('Support me on Ko-fi', '#141414', 'V8M8238Q2V');
                        kofiwidget2.draw();
                    </script>
                </div>
                <p class="text-cpr-text-muted text-xs italic max-w-md">
                    Tips are always appreciated, they will go towards the running of the server this is hosted on.
                </p>
            </div>

            <div class="help-body space-y-8 text-cpr-text-main">
                <section>
                    <h2 class="text-xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">1. Preparation &
                        Installation</h2>
                    <ul class="list-disc list-inside space-y-2 ml-4">
                        <li>Ensure you have the <a href="https://foundryvtt.com/packages/cyberpunk-red-core"
                                                   target="_blank" class="text-cpr-red hover:text-cpr-red underline">Cyberpunk
                                Red module</a> installed in Foundry VTT.
                        </li>
                        <li>Install the <strong>Cyberpunk Red Agent Bridge</strong> module in Foundry VTT by adding this
                            manifest URL in the module installer:
                            <div
                                class="bg-cpr-black/50 p-3 rounded border border-cpr-border font-mono text-cpr-red my-3 select-all text-sm break-all">
                                https://gitlab.com/surrealtech/Cyberpunk-Red-Agent-Bridge/-/raw/main/module.json
                            </div>
                        </li>
                        <li>Sign up and log in to this app. Your personal data is only used for authentication.</li>
                    </ul>
                </section>

                <section class="bg-cpr-black/50 p-6 rounded border-l-4 border-l-cpr-yellow">
                    <h2 class="text-xl font-bold text-cpr-yellow uppercase tracking-widest mb-2">Quick Connect: QR
                        Pairing</h2>
                    <p class="text-cpr-text-muted italic mb-4">The idea here is to minimise the connection errors. There is
                    much better error handling on the Agent side so any troubleshooting should be easier. With that said:</p>
                    <p class="mb-3">There are <strong>two QR codes</strong>, GM (for setup) and Player (for connection):</p>
                    <ul class="list-disc list-inside space-y-3 ml-4">
                        <li><strong>Foundry Setup Code (GM only)</strong> — In <strong>Settings</strong>, enter your
                            Foundry URL and generate an API Key — saving creates your session automatically. Hit
                            <strong>Get Foundry Setup Code</strong> and type the short code (e.g.
                            <code class="text-cpr-teal">KX7M-4PWD</code>) into <strong>Agent Bridge → Quick
                                Setup</strong> in Foundry — it works even when the Agent is on your phone. (A full
                            copy-paste code is also available for same-device setup.) Setup codes fetch your secret
                            API key, so never share one with players.
                        </li>
                        <li><strong>Pairing QR (per player)</strong> — Once your Bridge is online, open <strong>Agent
                                Bridge → Manage Characters</strong> and hit the <strong>QR button</strong> next to a
                            player. They open <strong>Settings → Join a Session</strong>, tap <strong>Scan to Link
                                Foundry</strong>, and point their camera at the code. Their Agent picks up the Channel
                            ID and Foundry URL, and their character is linked automatically.
                        </li>
                    </ul>
                    <p class="text-cpr-text-muted text-sm mt-4">No camera? A copyable link code appears under the scan
                        button — paste that instead. Codes are one-time use and expire after 5 minutes. Camera access
                        needs an <code class="text-cpr-red">https://</code> (or <code
                            class="text-cpr-red">localhost</code>) connection.</p>
                    <p class="mt-4">Prefer to do it by hand? The manual steps below still work as a fallback.</p>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">2. GM Setup</h2>
                    <p class="mb-4 text-cpr-text-muted text-sm">Pick <strong>Game Master</strong> when you register
                        (or switch role in Settings → Account Control) to unlock the GM flow.</p>
                    <ol class="list-decimal list-inside space-y-2 ml-4">
                        <li>As the Game Master, navigate to the <strong>Settings</strong> page.</li>
                        <li>Enter your <strong>Foundry URL</strong>, generate an <strong>API Key</strong>, and save —
                            your game session (and Channel ID) is created automatically.
                        </li>
                        <li>Use the <strong>Foundry Setup Code</strong> (above) to configure the Bridge in one step —
                            or manually paste the Channel ID and API Key into <strong>Configure Settings</strong> ->
                            <strong>Agent Bridge</strong> in Foundry.
                        </li>
                        <li>Share your <strong>session code</strong> with your players — or skip codes entirely and
                            hand them pairing QRs from <strong>Agent Bridge → Manage Characters</strong>.
                        </li>
                    </ol>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">3. Player Setup</h2>
                    <p class="mb-4">Players must link their accounts to the GM's session:</p>
                    <ol class="list-decimal list-inside space-y-2 ml-4">
                        <li>Log in to this Agent app and navigate to <strong>Settings</strong>.</li>
                        <li>Generate your own <strong>API Key</strong> and copy it. Enter this key into your Foundry
                            account settings provided by the GM (as below).
                        </li>
                        <li>Paste the <strong>Channel ID</strong> provided by your GM into the Channel ID field and
                            click <strong>Commit Sync</strong>.
                        </li>
                        <li>Within Foundry VTT, go to <strong>Configure Settings</strong> -> <strong>Agent
                                Bridge</strong> and enter your individual <strong>API Key</strong>.
                        </li>
                    </ol>
                </section>

                <section>
                    <h2 class="text-xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">4. Character
                        Assignment & Sync</h2>
                    <ol class="list-decimal list-inside space-y-2 ml-4">
                        <li>In Foundry VTT, the GM should open the <strong>Character Assignment</strong> menu (found in
                            the Agent Bridge module).
                        </li>
                        <li>The GM assigns a playable character to each player who has linked their API key.</li>
                        <li>Once assigned, the player's <strong>Agent Link</strong> (a long unique code) should appear
                            in the menu. The players must be online for this to appear, so dont worry if it doesn't
                            appear straight away.
                        </li>
                        <li>The player is now <strong>fully synced</strong>. Character data, stats, and rolls will flow
                            between Foundry and the Agent interface in real-time.
                        </li>
                    </ol>
                </section>
            </div>

            <div class="mt-12 border-t border-cpr-red/30 pt-6 text-center text-cpr-text-muted">
                <p>Want the full walkthrough — self-hosting, sharing over Tailscale, the QR handshake internals and
                    troubleshooting? Read the
                    <a href="https://gitlab.com/surrealtech/cyberpunk-red-agent/-/blob/main/wiki/" target="_blank"
                       class="text-cpr-red hover:text-cpr-red underline">complete documentation</a>.
                </p>
            </div>

            <div class="mt-10 border-t border-cpr-border/40 pt-6 text-center text-cpr-text-dim text-xs">
                <h2 class="text-sm font-bold text-cpr-text-muted uppercase tracking-widest mb-3">Credits</h2>
                <p>
                    Heading font <strong class="text-cpr-text-muted">"Cyberpnuk2"</strong> by junkohanhero —
                    <a href="https://www.dafont.com/junkohanhero.d1293" target="_blank"
                       class="text-cpr-red hover:text-cpr-red underline">dafont.com/junkohanhero.d1293</a>.
                </p>
                <p class="mt-1">
                    Vector artwork assistance via
                    <a href="https://www.vecteezy.com" target="_blank"
                       class="text-cpr-red hover:text-cpr-red underline">vecteezy.com</a>.
                </p>
            </div>

            <div class="mt-8 flex justify-center">
                <a href="{{ url()->previous() == url()->current() ? url('/') : url()->previous() }}"
                   class="cyber-button bg-cpr-black-elevated border-cpr-border text-lg px-12 py-3 uppercase font-bold">
                    Return
                </a>
            </div>
        </div>
    </div>

@endsection
