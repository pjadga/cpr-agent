@extends('layouts.cyberpunk')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center p-6 bg-[url('{{ asset('images/external/splash-square.png') }}')] bg-cover bg-center bg-no-repeat bg-blend-overlay bg-cpr-black/80">
    <div class="cpr-panel p-8 max-w-4xl w-full border-t-4 border-t-red-600 shadow-2xl backdrop-blur-sm overflow-y-auto max-h-[90vh]">
        <div class="mb-8 border-b border-cpr-red/30 pb-4">
            <h1 class="text-4xl font-black glow-red text-cpr-red uppercase tracking-tighter">How to Use</h1>
            <p class="text-cpr-text-muted italic"><br>Follow these steps to synchronize your Agent interface with Night City.</p>
        </div>

        <div class="space-y-8 text-cpr-text-main">
            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">1. Preparation & Installation</h2>
                <ul class="list-disc list-inside space-y-2 ml-4">
                    <li>Ensure you have the <a href="https://foundryvtt.com/packages/cyberpunk-red-core" target="_blank" class="text-cpr-red hover:text-cpr-red underline">Cyberpunk Red module</a> installed in Foundry VTT.</li>
                    <li>Install the <strong>Cyberpunk Red Agent Bridge</strong> module in Foundry VTT by adding this manifest URL in the module installer:
                        <div class="bg-cpr-black/50 p-3 rounded border border-cpr-border font-mono text-cpr-red my-3 select-all text-sm break-all">
                            https://gitlab.com/surrealtech/Cyberpunk-Red-Agent-Bridge/-/raw/main/module.json
                        </div>
                    </li>
                    <li>Sign up and log in to this app. Your personal data is only used for authentication.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">2. GM Setup</h2>
                <ol class="list-decimal list-inside space-y-2 ml-4">
                    <li>As the Game Master, navigate to the <strong>Settings</strong> page.</li>
                    <li>Generate an <strong>API Key</strong> and copy it. This needs to be entered into the Agent Bridge module in Foundry.</li>
                    <li>Copy your <strong>Channel ID</strong> from the Settings page.</li>
                    <li>In Foundry, go to <strong>Configure Settings</strong> -> <strong>Agent Bridge</strong> and paste the <strong>Channel ID</strong> and API Key.</li>
                    <li>Share this <strong>Channel ID</strong> with your players.</li>
                </ol>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">3. Player Setup</h2>
                <p class="mb-4">Players must link their accounts to the GM's session:</p>
                <ol class="list-decimal list-inside space-y-2 ml-4">
                    <li>Log in to this Agent app and navigate to <strong>Settings</strong>.</li>
                    <li>Generate your own <strong>API Key</strong> and copy it. Enter this key into your Foundry account settings provided by the GM (as below).</li>
                    <li>Paste the <strong>Channel ID</strong> provided by your GM into the Channel ID field and click <strong>Commit Sync</strong>.</li>
                    <li>Within Foundry VTT, go to <strong>Configure Settings</strong> -> <strong>Agent Bridge</strong> and enter your individual <strong>API Key</strong>.</li>
                </ol>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">4. Character Assignment & Sync</h2>
                <ol class="list-decimal list-inside space-y-2 ml-4">
                    <li>In Foundry VTT, the GM should open the <strong>Character Assignment</strong> menu (found in the Agent Bridge module).</li>
                    <li>The GM assigns a playable character to each player who has linked their API key.</li>
                    <li>Once assigned, the player's <strong>Agent Link</strong> (a long unique code) should appear in the menu. The players must be online for this to appear, so dont worry if it doesn't appear straight away.</li>
                    <li>The player is now <strong>fully synced</strong>. Character data, stats, and rolls will flow between Foundry and the Agent interface in real-time.</li>
                </ol>
            </section>
        </div>

        <div class="mt-12 flex justify-center">
            <a href="{{ url()->previous() == url()->current() ? url('/') : url()->previous() }}" class="cyber-button bg-cpr-black-elevated border-cpr-border text-lg px-12 py-3 uppercase font-bold">
                Return
            </a>
        </div>
    </div>
</div>
@endsection
