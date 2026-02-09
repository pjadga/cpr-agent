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
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">1. Preparation</h2>
                <ul class="list-disc list-inside space-y-2 ml-4">
                    <li>Ensure you have the <a href="https://foundryvtt.com/packages/cyberpunk-red-core" target="_blank" class="text-cpr-red hover:text-cpr-red underline">Cyberpunk Red module</a> installed in Foundry VTT.</li>
                    <li>Install the <a href="https://foundryvtt.com/packages/foundry-rest-api" target="_blank" class="text-cpr-red hover:text-cpr-red underline">ThreeHats API module</a> in your Foundry VTT instance.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">2. Account Setup</h2>
                <p class="mb-2">Sign up to this app. Your personal data is not used for any purposes other than authentication.</p>
                <p class="text-sm text-cpr-yellow italic">Note: Account deletion is accessible at any time through your account settings.</p>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">3. Configuration</h2>
                <ol class="list-decimal list-inside space-y-2 ml-4">
                    <li>Navigate to the <strong>Config</strong> page.</li>
                    <li>Input your <strong>Foundry URL</strong>.</li>
                    <li>Input your <strong>API Key</strong> (this must be the same one added to your Foundry ThreeHats module).</li>
                    <li>Click <strong>Save</strong>.</li>
                </ol>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">4. Foundry Module Settings</h2>
                <p>In the ThreeHats module settings within Foundry VTT, add the following as the relay link:</p>
                <div class="bg-cpr-black/50 p-4 rounded border border-cpr-border font-mono text-cpr-red my-4 select-all">
                    wss://agent-relay.surrealdisco.co.uk
                </div>
                <p class="text-sm text-cpr-text-muted">
                    This relay is self-hosted through IONOS on a VPS. Call frequency is monitored to improve the app and manage costs.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-bold text-cpr-yellow uppercase tracking-widest mb-4">5. Connecting Characters</h2>
                <ol class="list-decimal list-inside space-y-2 ml-4">
                    <li>Once the GM account is up and running, refresh the <strong>Config</strong> page.</li>
                    <li>A <strong>Foundry Client</strong> should now appear in the dropdown menu.</li>
                    <li>Selecting that client will load the <strong>Playable Characters</strong> and <strong>Player Owned characters</strong> currently in the session.</li>
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
