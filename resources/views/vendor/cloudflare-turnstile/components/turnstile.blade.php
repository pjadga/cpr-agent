@props([
    'id' => 'captcha',
    'nonce' => null,
])

@php
    if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $id)) {
        throw new InvalidArgumentException("The Turnstile ID [{$id}] must start with a letter or underscore, and can only contain alphanumeric or underscore characters.");
    }

    $model = $attributes->has('wire:model') ? $attributes->get('wire:model') : null;
@endphp

<div
    id="{{ $id }}"
    data-sitekey="{{ config('services.turnstile.key') }}"
    @if ($model)
        wire:ignore
        data-callback="{{ $id }}Callback"
        data-expired-callback="{{ $id }}ExpiredCallback"
        data-timeout-callback="{{ $id }}ExpiredCallback"
        {{ $attributes->filter(fn($value, $key) => ! in_array($key, ['data-callback', 'data-expired-callback', 'data-timeout-callback', 'wire:model', 'id']))->class(['cf-turnstile']) }}
    @else
        {{ $attributes->class(['cf-turnstile']) }}
    @endif
></div>

@if ($model)
    <script @if($nonce) nonce="{{ $nonce }}" @endif>
        document.addEventListener('livewire:initialized', () => {
            const widgetEl = document.getElementById({{ json_encode($id) }});
            const callbackKey = {{ json_encode($id . 'Callback') }};
            const expiredKey = {{ json_encode($id . 'ExpiredCallback') }};

            window[callbackKey] = function (token) {
                @this.set({{ json_encode($model) }}, token);
            };

            window[expiredKey] = function () {
                window.turnstile.reset(widgetEl);
            };

            const unwatch = @this.watch({{ json_encode($model) }}, (value, old) => {
                // If there was a value, and now there isn't, reset the Turnstile.
                if (old && !value) {
                    window.turnstile.reset(widgetEl);
                }
            });

            document.addEventListener('livewire:navigating', () => {
                unwatch();
                delete window[callbackKey];
                delete window[expiredKey];
            }, { once: true });
        });
    </script>
@endif
