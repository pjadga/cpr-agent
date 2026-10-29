@props(['name' => 'pattern', 'submit' => false])

{{-- A phone-style 3x3 pattern: drag across the dots (mouse or touch). The dots are numbered 1-9 row by
     row and the drawing is stored as "1-5-9-6" in a hidden input. With submit, drawing at least four
     dots submits the form. --}}
<div x-data="patternLock({ submit: @js((bool) $submit) })" {{ $attributes->merge(['class' => 'pattern-lock']) }}>
    <input type="hidden" name="{{ $name }}" x-ref="input">
    <svg viewBox="0 0 300 300" x-ref="pad" class="w-full max-w-[260px] mx-auto block select-none touch-none"
         role="img" aria-label="Pattern grid: drag across at least four dots"
         x-on:pointerdown.prevent="start($event)" x-on:pointermove="move($event)"
         x-on:pointerup="end()" x-on:pointercancel="end()" x-on:pointerleave="end()">
        <polyline x-bind:points="points" fill="none" stroke="currentColor" stroke-width="8"
                  stroke-linecap="round" stroke-linejoin="round" class="text-cpr-teal" opacity="0.8"/>
        {{-- Drawn here rather than with x-for, which doesn't work inside an SVG. --}}
        @foreach (range(1, 9) as $n)
            <circle cx="{{ 50 + (($n - 1) % 3) * 100 }}" cy="{{ 50 + intdiv($n - 1, 3) * 100 }}" r="10"
                    x-bind:r="path.includes({{ $n }}) ? 16 : 10" fill="currentColor"
                    x-bind:class="path.includes({{ $n }}) ? 'text-cpr-teal' : 'text-cpr-text-dim'"/>
        @endforeach
    </svg>
    <p class="text-[10px] text-center uppercase text-cpr-text-dim mt-2" x-text="message"></p>
</div>

@once
    <script>
        window.patternLock = function ({ submit }) {
            return {
                dots: [1, 2, 3, 4, 5, 6, 7, 8, 9].map(n => ({ n, x: 50 + ((n - 1) % 3) * 100, y: 50 + Math.floor((n - 1) / 3) * 100 })),
                path: [],
                drawing: false,
                pointer: null,
                message: 'Join at least 4 dots',
                get points() {
                    const line = this.path.map(n => this.dots[n - 1]).map(d => `${d.x},${d.y}`);
                    if (this.drawing && this.pointer) line.push(`${this.pointer.x},${this.pointer.y}`);
                    return line.join(' ');
                },
                at(event) {
                    const box = this.$refs.pad.getBoundingClientRect();
                    return { x: (event.clientX - box.left) * 300 / box.width, y: (event.clientY - box.top) * 300 / box.height };
                },
                hit(point) {
                    const dot = this.dots.find(d => Math.hypot(d.x - point.x, d.y - point.y) < 34);
                    if (!dot || this.path.includes(dot.n)) return;
                    // Passing straight over a dot between two others joins it too, as on a phone.
                    const last = this.path[this.path.length - 1];
                    if (last) {
                        const [r1, c1, r2, c2] = [(last - 1) / 3 | 0, (last - 1) % 3, (dot.n - 1) / 3 | 0, (dot.n - 1) % 3];
                        if ((r1 + r2) % 2 === 0 && (c1 + c2) % 2 === 0) {
                            const middle = ((r1 + r2) / 2) * 3 + (c1 + c2) / 2 + 1;
                            if (!this.path.includes(middle)) this.path.push(middle);
                        }
                    }
                    this.path.push(dot.n);
                },
                start(event) {
                    this.$refs.pad.setPointerCapture?.(event.pointerId);
                    this.path = [];
                    this.$refs.input.value = '';
                    this.drawing = true;
                    this.pointer = this.at(event);
                    this.hit(this.pointer);
                },
                move(event) {
                    if (!this.drawing) return;
                    this.pointer = this.at(event);
                    this.hit(this.pointer);
                },
                end() {
                    if (!this.drawing) return;
                    this.drawing = false;
                    if (this.path.length < 4) {
                        this.message = this.path.length ? 'Too short: join at least 4 dots' : 'Join at least 4 dots';
                        this.path = [];
                        return;
                    }
                    this.$refs.input.value = this.path.join('-');
                    this.message = submit ? 'Checking…' : 'Pattern drawn';
                    this.$dispatch('pattern-drawn', this.$refs.input.value);
                    if (submit) this.$root.closest('form').requestSubmit();
                },
                clear() {
                    this.path = [];
                    this.$refs.input.value = '';
                    this.message = 'Join at least 4 dots';
                },
            };
        };
    </script>
@endonce
