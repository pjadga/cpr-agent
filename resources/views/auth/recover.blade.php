<x-guest-layout>
    <div class="w-full mb-6">
        <h3 class="text-xl font-bold mb-2 text-cpr-yellow uppercase tracking-widest border-b border-cpr-yellow/30 pb-2">
            Recover Access</h3>
        <p class="text-[10px] text-cpr-yellow/80 uppercase italic">
            Enter the recovery email on your account and we'll send a one-time sign-in link.
        </p>
    </div>

    <div class="p-6 bg-cpr-black-deep/50 rounded-lg border border-cpr-border max-w-xl mx-auto">
        <x-auth-session-status class="mb-4" :status="session('status')"/>

        <form method="POST" action="{{ route('recover.send') }}" class="space-y-6">
            @csrf
            <div>
                <x-input-label for="email" :value="__('Recovery Email')" class="text-xs uppercase text-cpr-text-muted"/>
                <x-text-input id="email" class="cyber-input-field mt-1" type="email" name="email"
                              :value="old('email')" required autofocus autocomplete="email"/>
                <x-input-error :messages="$errors->get('email')" class="mt-2"/>
            </div>

            <button type="submit" class="cyber-button bg-cpr-red border-cpr-red w-full uppercase font-black text-lg py-3">
                Send Link
            </button>

            <p class="text-[10px] text-center text-cpr-text-dim uppercase">
                No recovery email on your account? Ask your gamemaster for your username.
            </p>
            <a class="block text-xs text-center text-cpr-text-dim hover:text-cpr-yellow uppercase transition-colors"
               href="{{ route('login') }}">{{ __('Back to sign-in') }}</a>
        </form>
    </div>
</x-guest-layout>
