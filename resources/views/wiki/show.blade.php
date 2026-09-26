@extends('layouts.cyberpunk')

@section('title', $page->title.', Wiki, '.config('app.name'))

@section('content')
    <div x-data="{ open: false }" @keydown.escape.window="open = false"
         class="wiki-shell min-h-screen bg-cpr-black-deep lg:flex">

        {{-- Mobile bar. Shows the current page so you always know where you are. --}}
        <header
            class="lg:hidden sticky top-0 z-30 flex items-center gap-2 h-14 px-3 bg-cpr-black border-b border-cpr-border">
            <button @click="open = true" aria-label="Open navigation"
                    class="p-2 -ml-1 text-cpr-text-main hover:text-cpr-text-bright">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>
            <span class="flex-1 truncate text-sm font-medium text-cpr-text-bright">{{ $page->title }}</span>
            <a href="{{ url('/') }}" aria-label="Back to Agent"
               class="p-2 -mr-1 text-cpr-text-muted hover:text-cpr-text-bright">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
        </header>

        {{-- Drawer backdrop --}}
        <div x-show="open" x-cloak x-transition.opacity @click="open = false"
             class="fixed inset-0 z-40 bg-black/70 lg:hidden"></div>

        {{-- Sidebar: off-canvas drawer on mobile, pinned full-height column on desktop --}}
        <aside x-cloak
               :class="open ? 'translate-x-0' : '-translate-x-full'"
               class="fixed top-0 bottom-0 left-0 z-50 flex w-72 shrink-0 flex-col bg-cpr-black border-r border-cpr-border
                      transition-transform duration-200 ease-out
                      lg:sticky lg:top-0 lg:bottom-auto lg:h-screen lg:self-start lg:translate-x-0">
            <div class="flex items-center justify-between gap-2 px-5 h-14 border-b border-cpr-border shrink-0">
                <a href="{{ route('wiki.index') }}"
                   class="font-mono text-sm font-bold uppercase tracking-widest text-cpr-red">CPR Agent Wiki</a>
                <button @click="open = false" aria-label="Close navigation"
                        class="lg:hidden p-1 -mr-1 text-cpr-text-muted hover:text-cpr-text-bright">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto py-4">
                <p class="px-5 pb-2 text-[0.6875rem] uppercase tracking-[0.2em] text-cpr-text-dim">Documentation</p>
                @foreach ($pages as $item)
                    @php($active = $item->slug === $page->slug)
                    <a href="{{ route('wiki.show', $item->slug) }}" @click="open = false"
                       @class([
                           'block border-l-2 py-2 pl-[1.125rem] pr-4 text-sm leading-snug transition-colors',
                           'border-cpr-red bg-cpr-black-soft text-cpr-text-bright font-medium' => $active,
                           'border-transparent text-cpr-text-main hover:bg-cpr-black-soft hover:text-cpr-text-bright' => ! $active,
                       ])
                       @if ($active) aria-current="page" @endif>
                        {{ $item->title }}
                    </a>
                @endforeach
            </nav>

            <div class="px-5 py-4 border-t border-cpr-border shrink-0">
                <a href="{{ url('/') }}"
                   class="flex items-center gap-2 text-xs uppercase tracking-widest text-cpr-text-dim hover:text-cpr-text-main">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Agent
                </a>
            </div>
        </aside>

        <main class="min-w-0 flex-1 px-5 py-8 sm:px-8 lg:px-12 lg:py-12">
            <div class="mx-auto max-w-[44rem]">
                <p class="mb-5 text-[0.6875rem] uppercase tracking-[0.2em] text-cpr-text-dim">{{ $page->title }}</p>

                <div class="wiki-prose">
                    {!! $page->contents !!}
                </div>

                @if ($previous || $next)
                    <nav class="mt-14 grid gap-3 border-t border-cpr-border-dark pt-6 sm:grid-cols-2">
                        @if ($previous)
                            <a href="{{ route('wiki.show', $previous->slug) }}"
                               class="group border border-cpr-border-dark px-4 py-3 hover:border-cpr-border-muted">
                                <span class="block text-[0.6875rem] uppercase tracking-widest text-cpr-text-dim">Previous</span>
                                <span class="mt-0.5 block text-sm text-cpr-text-main group-hover:text-cpr-text-bright">{{ $previous->title }}</span>
                            </a>
                        @endif

                        @if ($next)
                            <a href="{{ route('wiki.show', $next->slug) }}"
                               class="group border border-cpr-border-dark px-4 py-3 hover:border-cpr-border-muted sm:col-start-2 sm:text-right">
                                <span class="block text-[0.6875rem] uppercase tracking-widest text-cpr-text-dim">Next</span>
                                <span class="mt-0.5 block text-sm text-cpr-text-main group-hover:text-cpr-text-bright">{{ $next->title }}</span>
                            </a>
                        @endif
                    </nav>
                @endif
            </div>
        </main>
    </div>
@endsection
