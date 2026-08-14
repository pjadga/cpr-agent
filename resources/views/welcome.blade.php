@extends('layouts.cyberpunk')

@section('content')
    <div
        class="min-h-screen flex flex-col items-center justify-center p-6 bg-[url('{{ asset('images/external/splash-square.png') }}')] bg-cover bg-center bg-no-repeat bg-blend-overlay bg-cpr-black/80">
        <div class="cpr-panel p-12 max-w-2xl w-full border-t-4 border-t-red-600 shadow-2xl backdrop-blur-sm">
            <div class="text-center">
                <h1 class="text-6xl font-black mb-6 glow-red text-cpr-red uppercase tracking-tighter">Agent</h1>
                <p class="text-xl mb-12 text-cpr-text-muted italic font-medium">Connecting you to Night City...</p>

                <div class="flex flex-col sm:flex-row gap-6 justify-center">
                    @auth
                        <a href="{{ url('/dashboard') }}"
                           class="cyber-button bg-cpr-lime-dark border-cpr-lime text-lg px-12 py-4 uppercase font-bold text-center">
                            Connect
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="cyber-button bg-cpr-red border-cpr-red text-lg px-12 py-4 uppercase font-bold text-center">
                            Initiate Link
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}"
                               class="cyber-button bg-cpr-black-elevated border-cpr-border text-lg px-12 py-4 uppercase font-bold text-center">
                                Register Bio-ID
                            </a>
                        @endif
                    @endauth
                </div>

                <div class="mt-8">
                    <a href="{{ route('how-to-use') }}"
                       class="text-cpr-text-muted hover:text-cpr-red uppercase tracking-widest text-sm transition-colors">
                        How to Use
                    </a>
                </div>

                {{-- Ko-fi support button — inside the box, below "How to Use". Only on the
                     official hosted instance; self-hosted / local instances hide it here
                     (it stays on the How to Use page). Colour matches the dark panel. --}}
                @if (request()->getHost() === 'agent.surrealdisco.co.uk')
                    <div class="flex justify-center mt-8">
                        <p class="text-cpr-text-muted text-xs italic block max-w-md">
                            Tips are always appreciated but never required, they will go towards the running of the server this is hosted on.
                        </p>
                    </div>
                    <div class="flex justify-center mt-8">
                        <script type='text/javascript' src='https://storage.ko-fi.com/cdn/widget/Widget_2.js' @cspNonce></script>
                        <script type='text/javascript' @cspNonce>
                            kofiwidget2.init('Support me on Ko-fi', '#141414', 'V8M8238Q2V');
                            kofiwidget2.draw();
                        </script>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
