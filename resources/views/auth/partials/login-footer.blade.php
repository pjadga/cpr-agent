<label class="flex items-center">
    <input type="checkbox" name="remember"
           class="rounded bg-cpr-black-elevated border-cpr-border text-cpr-pink-dark focus:ring-cpr-pink">
    <span class="ms-2 text-sm text-cpr-text-muted italic">Stay connected</span>
</label>

@if(config('services.turnstile.enabled'))
    <div class="flex flex-col items-center">
        <x-turnstile/>
        <x-input-error :messages="$errors->get('cf-turnstile-response')" class="mt-2"/>
    </div>
@endif

<button type="submit" class="cyber-button bg-cpr-red border-cpr-red w-full uppercase font-black text-lg py-3">
    Unlock
</button>
