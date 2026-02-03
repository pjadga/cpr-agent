@extends('layouts.cyberpunk')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center p-6 bg-[url('{{ asset('images/external/splash-square.png') }}')] bg-cover bg-center bg-no-repeat bg-blend-overlay bg-cpr-black/80">
    <div class="cpr-panel p-12 max-w-2xl w-full border-t-4 border-t-red-600 shadow-2xl backdrop-blur-sm">
        <div class="text-center">
            <h1 class="text-6xl font-black mb-6 glow-red text-cpr-red uppercase tracking-tighter">Cyberpunk Interface</h1>
            <p class="text-xl mb-12 text-cpr-text-muted italic font-medium">Connecting you to the Night City data stream...</p>

            <div class="flex flex-col sm:flex-row gap-6 justify-center">
                @auth
                    <a href="{{ url('/dashboard') }}" class="cyber-button bg-cpr-lime-dark border-cpr-lime text-lg px-12 py-4 uppercase font-bold text-center">
                        Enter Matrix
                    </a>
                @else
                    <a href="{{ route('login') }}" class="cyber-button bg-cpr-red border-cpr-red text-lg px-12 py-4 uppercase font-bold text-center">
                        Initiate Link
                    </a>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="cyber-button bg-cpr-black-elevated border-cpr-border text-lg px-12 py-4 uppercase font-bold text-center">
                            Register Bio-ID
                        </a>
                    @endif
                @endauth
            </div>

            <div class="mt-8">
                <a href="{{ route('how-to-use') }}" class="text-cpr-text-muted hover:text-cpr-red uppercase tracking-widest text-sm transition-colors">
                    How to Use
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
