<button {{ $attributes->merge(['type' => 'submit', 'class' => 'cyber-button bg-red-600 border-red-500 uppercase font-bold text-sm']) }}>
    {{ $slot }}
</button>
