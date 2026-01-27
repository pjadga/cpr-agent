@extends('layouts.cyberpunk')

@section('content')
<div class="min-h-screen flex flex-col items-center justify-center p-6 bg-[url('{{ asset('images/external/login-bg.jpg') }}')] bg-cover bg-center bg-no-repeat bg-blend-overlay bg-black/80">
    <div class="cpr-panel p-12 max-w-2xl w-full border-t-4 border-t-red-600 shadow-2xl backdrop-blur-sm">
        <div class="text-center">
            <h1 class="text-6xl font-black mb-6 glow-red text-red-600 uppercase tracking-tighter">Cyberpunk Interface</h1>
            <p class="text-xl mb-12 text-gray-400 italic font-medium">Connecting you to the Night City data stream...</p>

            <div class="flex flex-col sm:flex-row gap-6 justify-center">
                @auth
                    <a href="{{ url('/dashboard') }}" class="cyber-button bg-lime-600 border-lime-500 text-lg px-12 py-4 uppercase font-bold text-center">
                        Enter Matrix
                    </a>
                @else
                    <a href="{{ route('login') }}" class="cyber-button bg-red-600 border-red-500 text-lg px-12 py-4 uppercase font-bold text-center">
                        Initiate Link
                    </a>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="cyber-button bg-gray-800 border-gray-700 text-lg px-12 py-4 uppercase font-bold text-center">
                            Register Bio-ID
                        </a>
                    @endif
                @endauth
            </div>

            <div class="mt-8">
                <a href="{{ route('how-to-use') }}" class="text-gray-400 hover:text-red-500 uppercase tracking-widest text-sm transition-colors">
                    How to Use
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
