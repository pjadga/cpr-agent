<x-guest-layout>
    <div class="max-w-md mx-auto">
        <h2 class="text-2xl font-bold mb-4 glow-red text-cpr-red uppercase tracking-tighter">Access Recovery</h2>

        <div class="mb-6 text-sm text-cpr-text-muted leading-relaxed italic">
            {{ __('Lost your access code? Enter your Agent ID (email) and we will transmit a reset link to your terminal.') }}
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form id="forgot-password-form" method="POST" action="{{ route('password.email') }}" class="space-y-6">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Agent ID (Email)')" class="text-xs uppercase text-cpr-text-muted" />
                <x-text-input id="email" class="cyber-input-field mt-1" type="email" name="email" :value="old('email')" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="mt-4 flex flex-col items-center">
                <x-turnstile />
                <x-input-error :messages="$errors->get('cf-turnstile-response')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="cyber-button bg-cpr-red border-cpr-red w-full py-3 uppercase font-black text-lg">
                    Transmit Reset Link
                </button>
            </div>
        </form>

        <div class="mt-6 text-center">
            <a href="{{ route('login') }}" class="text-xs text-cpr-text-dim hover:text-cpr-yellow uppercase transition-colors">
                Return to Login
            </a>
        </div>
    </div>
</x-guest-layout>
