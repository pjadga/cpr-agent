@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-cpr-text-muted']) }}>
    {{ $value ?? $slot }}
</label>
