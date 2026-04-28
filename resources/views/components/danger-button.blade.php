<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-cpr-red border border-transparent rounded-md font-semibold text-xs text-cpr-yellow uppercase tracking-widest hover:bg-cpr-red active:bg-cpr-red-dark focus:outline-none focus:ring-2 focus:ring-cpr-red focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
