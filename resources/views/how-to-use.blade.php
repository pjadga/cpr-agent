@extends('layouts.cyberpunk')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center p-6 bg-[url('{{ asset('images/external/splash-square.png') }}')] bg-cover bg-center bg-no-repeat bg-blend-overlay bg-cpr-black/80">
    <div class="cpr-panel p-8 max-w-4xl w-full border-t-4 border-t-red-600 shadow-2xl backdrop-blur-sm overflow-y-auto max-h-[90vh]">
        <div class="mb-8 border-b border-cpr-red/30 pb-4">
            <h1 class="text-4xl font-black glow-red text-cpr-red uppercase tracking-tighter">How to Use</h1>
            <p class="text-cpr-text-muted italic">Follow these steps to synchronize your Agent interface with Night City.</p>
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
                    <li>Generate a <strong>Connection Key (API Key)</strong> and copy it.</li>
                    <li>Input your <strong>Foundry URL</strong> and click <strong>Update Core Settings</strong>.</li>
                    <li>Check the connection to ensure it is active and communicating with your Foundry instance.</li>
                </ol>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">3. Player Setup</h2>
                <p class="mb-4">Players must link their own Agent accounts to synchronize their characters:</p>
                <ol class="list-decimal list-inside space-y-2 ml-4">
                    <li>Have each player sign up and log in to this Agent app.</li>
                    <li>They must generate their own <strong>Connection Key</strong> in their personal Settings page.</li>
                    <li>Within Foundry VTT, players must go to <strong>Configure Settings</strong> -> <strong>Agent Bridge</strong> and enter their individual API Key.</li>
                </ol>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">4. Character Assignment</h2>
                <ol class="list-decimal list-inside space-y-2 ml-4">
                    <li>In Foundry VTT, the GM should use the <strong>Manage Characters</strong> tool within the Agent Bridge module.</li>
                    <li>Assign playable characters to the players who have successfully linked their API keys.</li>
                    <li>This creates the secure link between the Foundry Actor and the Agent Interface.</li>
                </ol>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">5. Final Synchronization</h2>
                <ol class="list-decimal list-inside space-y-2 ml-4">
                    <li>Return to the <strong>Settings</strong> page in this app.</li>
                    <li>Use the <strong>Agent Sync</strong> refresh button to pull the updated player and character assignments.</li>
                    <li>Once the biometric signature (Actor) appears in the dropdown, select it and click <strong>Commit Sync</strong>.</li>
                    <li>Your character data will now flow between Foundry and this Agent interface in real-time.</li>
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
