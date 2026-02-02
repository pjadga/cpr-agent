@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-cpr-black-elevated border-cpr-border text-cpr-text-main focus:border-cpr-pink focus:ring-cpr-pink rounded-md shadow-sm placeholder-cpr-text-dim text-base']) }}>
