@extends('layouts.cyberpunk')

@section('content')
    <div class="min-h-screen bg-cpr-black-deep text-cpr-text-main">
        <header class="sticky top-0 z-40 border-b border-cpr-yellow-dark bg-cpr-black/90 backdrop-blur-md">
            <div class="mx-auto max-w-5xl px-4 py-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-xl font-black uppercase tracking-tight text-cpr-yellow">
                            Design Brief
                            <span
                                class="ml-2 text-[10px] font-mono font-bold text-cpr-yellow uppercase tracking-[0.3em]">CPR UI</span>
                        </h1>
                        <p class="mt-1 text-xs text-cpr-text-muted">
                            A living reference for colours, surfaces, and UI components used across the app UI.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="#palette"
                           class="border border-cpr-border px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-cpr-text-main hover:border-cpr-border-muted hover:text-cpr-yellow">
                            Palette
                        </a>
                        <a href="#components"
                           class="border border-cpr-border px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-cpr-text-main hover:border-cpr-border-muted hover:text-cpr-yellow">
                            Components
                        </a>
                        <a href="#patterns"
                           class="border border-cpr-border px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-cpr-text-main hover:border-cpr-border-muted hover:text-cpr-yellow">
                            Patterns
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <main id="app" class="mx-auto max-w-5xl px-4 py-8 space-y-10" v-cloak>
            {{-- =========================
                 Palette
            ========================== --}}
            <section id="palette" class="space-y-4">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-black uppercase tracking-[0.2em] text-cpr-yellow">Colour Palette</h2>
                        <p class="mt-1 text-xs text-cpr-text-dim">
                            These swatches map directly to the CSS variables used in the app.
                        </p>
                    </div>

                    <div class="text-[10px] font-mono uppercase tracking-widest text-cpr-text-dim">
                        Source: <span class="text-cpr-text-main">resources/css/app.css</span>
                    </div>
                </div>

                <div class="grid gap-6">
                    {{-- Brand --}}
                    <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-black uppercase tracking-widest text-cpr-yellow">Brand / Primary</h3>
                            <span class="text-[10px] font-mono text-cpr-text-dim">Red + Yellow drive most “action” UI</span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
                            @php
                                $brand = [
                                    ['name' => 'cpr-red', 'var' => '--cpr-red', 'hex' => '#da0000'],
                                    ['name' => 'cpr-red-dark', 'var' => '--cpr-red-dark', 'hex' => '#a30000'],
                                    ['name' => 'cpr-yellow', 'var' => '--cpr-yellow', 'hex' => '#fcee0a'],
                                    ['name' => 'cpr-yellow-dark', 'var' => '--cpr-yellow-dark', 'hex' => '#cfc100'],
                                ];
                            @endphp

                            @foreach ($brand as $c)
                                <div class="border border-cpr-border bg-cpr-black p-3">
                                    <div class="h-10 border border-cpr-border"
                                         style="background: var({{ $c['var'] }});"></div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <div class="text-[10px] font-bold uppercase tracking-widest text-cpr-yellow">{{ $c['name'] }}</div>
                                    </div>
                                    <div class="mt-1 text-[10px] font-mono text-cpr-text-dim">
                                        <div>{{ $c['var'] }}</div>
                                        <div class="text-cpr-text-main">{{ $c['hex'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Accents --}}
                    <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-black uppercase tracking-widest text-cpr-teal">Accents</h3>
                            <span class="text-[10px] font-mono text-cpr-text-dim">Teal handles tech, secondary, and informative states.</span>
                        </div>

                        @php
                            $accents = [
                                ['name' => 'cpr-teal', 'var' => '--cpr-teal', 'hex' => '#14b8a6'],
                                ['name' => 'cpr-teal-dark', 'var' => '--cpr-teal-dark', 'hex' => '#0d9488'],
                            ];
                        @endphp

                        <div class="mt-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                            @foreach ($accents as $c)
                                <div class="border border-cpr-border bg-cpr-black p-3">
                                    <div class="h-8 border border-cpr-border"
                                         style="background: var({{ $c['var'] }});"></div>
                                    <div class="mt-2 text-[10px] font-bold uppercase tracking-widest text-cpr-yellow">{{ $c['name'] }}</div>
                                    <div class="mt-1 text-[10px] font-mono text-cpr-text-dim">
                                        <div>{{ $c['var'] }}</div>
                                        <div class="text-cpr-text-main">{{ $c['hex'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Neutrals --}}
                    <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-black uppercase tracking-widest text-cpr-yellow">Neutrals / Surfaces</h3>
                            <span class="text-[10px] font-mono text-cpr-text-dim">Base theme supports night + day modes</span>
                        </div>

                        @php
                            $neutrals = [
                                ['name' => 'cpr-black', 'var' => '--cpr-black', 'value' => '#0a0a0a'],
                                ['name' => 'cpr-black-deep', 'var' => '--cpr-black-deep', 'value' => '#050505'],
                                ['name' => 'cpr-black-soft', 'var' => '--cpr-black-soft', 'value' => 'rgba(17,17,17,0.51)'],
                                ['name' => 'cpr-black-elevated', 'var' => '--cpr-black-elevated', 'value' => 'rgba(39,33,33,0.73)'],
                                ['name' => 'cpr-border', 'var' => '--cpr-border', 'value' => '#2a2a2a'],
                                ['name' => 'cpr-border-muted', 'var' => '--cpr-border-muted', 'value' => '#374151'],
                                ['name' => 'cpr-text-main', 'var' => '--cpr-text-main', 'value' => '#cdcdcd'],
                                ['name' => 'cpr-text-muted', 'var' => '--cpr-text-muted', 'value' => '#96b1ca'],
                                ['name' => 'cpr-text-dim', 'var' => '--cpr-text-dim', 'value' => '#636e73'],
                                ['name' => 'cpr-text-bright', 'var' => '--cpr-text-bright', 'value' => '#f5f5f5'],
                            ];
                        @endphp

                        <div class="mt-4 grid grid-cols-2 md:grid-cols-5 gap-3">
                            @foreach ($neutrals as $c)
                                <div class="border border-cpr-border bg-cpr-black p-3">
                                    <div class="h-8 border border-cpr-border"
                                         style="background: var({{ $c['var'] }});"></div>
                                    <div class="mt-2 text-[10px] font-bold uppercase tracking-widest text-cpr-yellow">{{ $c['name'] }}</div>
                                    <div class="mt-1 text-[10px] font-mono text-cpr-text-dim">
                                        <div>{{ $c['var'] }}</div>
                                        <div class="text-cpr-text-main">{{ $c['value'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            {{-- =========================
                 Components
            ========================== --}}
            <section id="components" class="space-y-4">
                <h2 class="text-sm font-black uppercase tracking-[0.2em] text-cpr-yellow">Components</h2>

                <div class="grid md:grid-cols-2 gap-4">
                    <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-cpr-red">Navigation Button</h3>
                        <p class="mt-2 text-xs text-cpr-text-muted">
                            Icon + label; active state flips to <span class="text-cpr-red font-bold">cpr-red</span>.
                        </p>

                        <div class="mt-4 flex gap-3">
                            <div class="border border-cpr-border bg-cpr-black p-3 w-28 text-center flex justify-center">
                                <nav-btn icon="user" label="Inactive" :active="false"></nav-btn>
                            </div>
                            <div class="border border-cpr-border bg-cpr-black p-3 w-28 text-center flex justify-center">
                                <nav-btn icon="user" label="Active" :active="true"></nav-btn>
                            </div>
                        </div>
                    </div>

                    <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-cpr-yellow">Resource Counter</h3>
                        <p class="mt-2 text-xs text-cpr-text-muted">
                            Compact stat tile (label + large mono number). Colour is passed as a class (commonly
                            red/cyan).
                        </p>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <resource-counter label="HIT POINTS" :current="32" :max="40"
                                              color="text-cpr-red"></resource-counter>
                            <resource-counter label="HUMANITY" :current="58" :max="70"
                                              color="text-cpr-teal"></resource-counter>
                        </div>
                    </div>

                    <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-cpr-yellow">Simple Stat</h3>
                        <p class="mt-2 text-xs text-cpr-text-muted">
                            Compact stat tile (value: white → Teal hover; label: Red).
                        </p>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <simple-stat label="INT" :value="8"></simple-stat>
                            <simple-stat label="REF" :value="7"></simple-stat>
                        </div>
                    </div>

                    <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-cpr-teal">Program Row
                            (Netspace)</h3>
                        <p class="mt-2 text-xs text-cpr-text-muted">
                            Teal is the “netrunner/tech online” signal. Rows often show a left accent bar + active glow.
                        </p>

                        <div class="mt-4 space-y-2">
                            <program-row
                                :prog="{
                                    name: 'Decrypter',
                                    img: '',
                                    system: {
                                        class: 'Program',
                                        isRezzed: true,
                                        description: { value: 'Used to bypass basic encryption.' },
                                        price: { market: 500 }
                                    }
                                }"
                                category="Program"
                                :is-expanded="false">
                            </program-row>
                        </div>
                    </div>
                </div>
            </section>

            {{-- =========================
                 Patterns & States
            ========================== --}}
            <section id="patterns" class="space-y-4">
                <h2 class="text-sm font-black uppercase tracking-[0.2em] text-cpr-yellow">Patterns & States</h2>

                <div class="grid lg:grid-cols-3 gap-4">
                    <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-cpr-yellow">Surfaces</h3>
                        <ul class="mt-3 space-y-2 text-xs text-cpr-text-muted">
                            <li><span class="text-cpr-yellow font-bold">Base:</span> deep black backgrounds.</li>
                            <li><span class="text-cpr-yellow font-bold">Panels:</span> clipped corner + border.</li>
                            <li><span class="text-cpr-yellow font-bold">Elevated:</span> subtle lighter “device glass” surface.</li>
                        </ul>
                    </div>

                    <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-cpr-yellow">Motion</h3>
                        <ul class="mt-3 space-y-2 text-xs text-cpr-text-muted">
                            <li><span class="text-cpr-yellow font-bold">fade</span> for tab transitions.</li>
                            <li><span class="text-cpr-yellow font-bold">expand</span> for disclosure/accordion rows.</li>
                            <li><span class="text-cpr-yellow font-bold">slide-right</span> for side panels.</li>
                            <li><span class="text-cpr-yellow font-bold">slide-up</span> for bottom combat panel.</li>
                        </ul>
                    </div>

                    <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                        <h3 class="text-xs font-black uppercase tracking-widest text-cpr-yellow">Colour Semantics</h3>
                        <ul class="mt-3 space-y-2 text-xs text-cpr-text-muted">
                            <li><span class="text-cpr-red font-bold">Red:</span> primary action, danger, combat,
                                top-level titles.
                            </li>
                            <li><span class="text-cpr-yellow font-bold">Yellow:</span> role, currency, highlight,
                                second-level titles.
                            </li>
                            <li><span class="text-cpr-teal font-bold">Teal:</span> tech/netspace, active systems,
                                secondary elements.
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="cpr-panel bg-[var(--cpr-black-soft)] p-4">
                    <h3 class="text-xs font-black uppercase tracking-widest text-cpr-yellow">Common UI Blocks (quick checklist)</h3>

                    <div class="mt-4 grid md:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                        <div class="border border-cpr-border bg-cpr-black p-3">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-cpr-text-dim">Modal
                                header
                            </div>
                            <div class="mt-2 border border-cpr-border bg-cpr-black/40 p-2 flex justify-between">
                                <span
                                    class="text-[10px] font-bold uppercase tracking-widest text-cpr-text-dim">Title</span>
                                <span class="text-cpr-text-muted">×</span>
                            </div>
                        </div>

                        <div class="border border-cpr-border bg-cpr-black p-3">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-cpr-text-dim">Primary CTA
                            </div>
                            <div class="mt-2">
                                <button class="w-full py-2 bg-cpr-red text-black text-xs font-black uppercase">Execute
                                </button>
                            </div>
                        </div>

                        <div class="border border-cpr-border bg-cpr-black p-3">
                            <div class="text-[10px] font-bold uppercase tracking-widest text-cpr-text-dim">Secondary /
                                Outline
                            </div>
                            <div class="mt-2">
                                <button
                                    class="w-full py-2 border-2 border-cpr-red text-cpr-red text-xs font-black uppercase">
                                    Roll Damage
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mt-8">
                <h2 class="text-sm font-black uppercase tracking-[0.2em] text-cpr-red">Character Sheet Components</h2>
                <p class="mt-2 text-xs text-cpr-text-muted">Overview of UI components used in the Character Sheet.
                    Colors follow Red (top-level), Yellow (second-level), Teal (secondary/tech) hierarchy.</p>
                <div class="mt-6 space-y-12">
                    {{-- Stats & Resources --}}
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-cpr-yellow border-b border-cpr-border pb-1">
                            Stats & Resources</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Resource Counter</div>
                                <resource-counter label="HIT POINTS" :current="25" :max="40"
                                                  color="text-cpr-red"></resource-counter>
                            </div>
                            <div class="space-y-2">
                                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Simple Stat</div>
                                <simple-stat label="DEX" :value="6"></simple-stat>
                            </div>
                            <div class="space-y-2">
                                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Adjustable Stat</div>
                                <adjustable-stat label="LUCK" :current="4" :max="7"></adjustable-stat>
                            </div>
                        </div>
                    </div>

                    {{-- Items & Rows --}}
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-cpr-yellow border-b border-cpr-border pb-1">
                            Items & Inventory</h3>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Weapon Item Row</div>
                                <item-row
                                    :item="{
                                        name: 'Heavy Pistol',
                                        img: '',
                                        system: {
                                            damage: '3d6',
                                            rof: 2,
                                            magazine: { value: 8, max: 8 },
                                            price: { market: 100 },
                                            description: { value: 'A reliable sidearm.' }
                                        }
                                    }"
                                    category="weapon">
                                </item-row>
                            </div>
                            <div class="space-y-2">
                                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Cyberware Item Row</div>
                                <item-row
                                    :item="{
                                        name: 'Neural Link',
                                        img: '',
                                        system: {
                                            installedItems: { usedSlots: 0, slots: 5 },
                                            price: { market: 500 },
                                            description: { value: 'Required for most other cyberware.' }
                                        }
                                    }"
                                    category="cyberware">
                                </item-row>
                            </div>
                        </div>
                    </div>

                    {{-- Netrunner --}}
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-cpr-yellow border-b border-cpr-border pb-1">
                            Netrunner Systems</h3>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Program Row (Rezzed)
                                </div>
                                <program-row
                                    :prog="{
                                        name: 'Armor',
                                        img: '',
                                        system: {
                                            class: 'Defender',
                                            isRezzed: true,
                                            rez: { value: 7, max: 7 },
                                            def: 2,
                                            price: { market: 600 },
                                            description: { value: 'Reduces damage to your Rez.' }
                                        }
                                    }"
                                    category="Defender">
                                </program-row>
                            </div>
                        </div>
                    </div>

                    {{-- Tab Components --}}
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-cpr-yellow border-b border-cpr-border pb-1">
                            Tab Layouts</h3>
                        <p class="text-[10px] text-cpr-text-dim italic">These are the top-level tab components. They
                            require a full character data object.</p>
                        <div class="space-y-8">
                            <div class="space-y-2">
                                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Stats Tab Example</div>
                                <div
                                    class="border border-cpr-border bg-cpr-black-deep h-[400px] overflow-y-auto relative p-2">
                                    <stats-tab
                                        active-tab="stats"
                                        :char-data="{
                                            data: {
                                                system: {
                                                    derivedStats: {
                                                        hp: { value: 30, max: 40 },
                                                        humanity: { value: 50, max: 70 },
                                                        deathSave: { penalty: 0 }
                                                    },
                                                    wealth: { value: 1250 },
                                                    improvementPoints: { value: 150 },
                                                    reputation: { value: 3 },
                                                    items: []
                                                }
                                            }
                                        }"
                                        :stats="{
                                            int: { value: 8 }, ref: { value: 7 }, dex: { value: 6 }, tech: { value: 8 },
                                            cool: { value: 5 }, will: { value: 6 }, move: { value: 5 }, body: { value: 7 }
                                        }"
                                        :luck="{ value: 4, max: 7 }"
                                        :emp="{ value: 5, max: 5 }"
                                        :grouped-equipped="{ weapon: [], armor: [], gear: [] }">
                                    </stats-tab>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Utility --}}
                    <div class="space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-cpr-yellow border-b border-cpr-border pb-1">
                            Navigation & Utility</h3>
                        <div class="flex gap-8">
                            <div class="space-y-2">
                                <div class="text-[10px] text-cpr-text-dim uppercase font-bold">Nav Button</div>
                                <div class="bg-cpr-black p-4 border border-cpr-border">
                                    <nav-btn icon="shield" label="Armor" :active="false"></nav-btn>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <footer class="pt-4 pb-10 text-center text-[10px] font-mono text-cpr-text-dim uppercase tracking-widest">
                End of brief — tweak freely.
            </footer>
        </main>
    </div>
@endsection
