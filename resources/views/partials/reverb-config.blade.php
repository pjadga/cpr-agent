{{-- Runtime Echo/Reverb config. bootstrap.js prefers this over the values
     baked into the Vite bundle, so one prebuilt asset set works on any host
     (self-hosted, FrankenPHP standalone, etc.). --}}
<script @cspNonce>
    window.reverbConfig = @js(array_filter(config('broadcasting.connections.reverb.frontend', []), fn ($v) => $v !== null));
</script>
