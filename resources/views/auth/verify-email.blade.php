<x-guest-layout>
    <div class="max-w-md mx-auto">
        <h2 class="text-2xl font-bold mb-4 glow-red text-cpr-red uppercase tracking-tighter">ID Verification
            Pending</h2>

        <div class="mb-6 text-sm text-cpr-text-muted leading-relaxed italic">
            {{ __('Welcome to the Net. Before you can interface with Night City systems, we need to verify your Agent ID. Check your terminal for the verification link we transmitted. If it didn\'t arrive, we can re-transmit.') }}
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="mb-6 font-medium text-sm text-cpr-teal uppercase tracking-widest glow-teal">
                {{ __('New transmission sent. Check your Agent inbox.') }}
            </div>
        @endif

        <div class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <form method="POST" action="{{ route('verification.send') }}" class="w-full sm:w-auto">
                @csrf

                <x-primary-button class="w-full">
                    {{ __('Re-transmit Link') }}
                </x-primary-button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto">
                @csrf

                <button type="submit"
                        class="w-full text-xs text-cpr-text-dim hover:text-cpr-red uppercase transition-colors tracking-widest">
                    {{ __('Terminate Session') }}
                </button>
            </form>
        </div>
    </div>
</x-guest-layout>
