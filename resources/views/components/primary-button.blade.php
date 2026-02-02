<button {{ $attributes->merge(['type' => 'submit', 'class' => 'cyber-button bg-cpr-red border-cpr-red uppercase font-bold text-sm']) }}>
    {{ $slot }}
</button>
