<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-cpr-black border border-cpr-border rounded-md font-semibold text-xs text-cpr-text-dim uppercase tracking-widest shadow-sm hover:bg-cpr-black-elevated focus:outline-none focus:ring-2 focus:ring-cpr-cyan focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
