<!DOCTYPE html>
<html class="theme-night" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="viewport-fit=cover, width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
    {{-- No csrf-token meta: the embed route runs without StartSession (see
         routes/web.php), so csrf_token() has no session to read. Embed is
         stateless ,  all writes go over postMessage, not CSRF-guarded POSTs. --}}
    @cspMetaTag

    <title>Agent</title>

    {{-- Flag embedded mode BEFORE the app module loads so bootstrap.js takes the
         postMessage transport path (no Reverb). channel_id is unused in embed. --}}
    <script @cspNonce>
        window.__CPR_EMBED = true;
        window.AgentConfig = { channel_id: null };
    </script>

    <!-- Fonts -->
    <link rel="preload" as="font" type="font/ttf" href="{{ Vite::asset('resources/fonts/Cybrpnuk2.ttf') }}" crossorigin>
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/hack-font@3/build/web/hack.css">
    <link href="https://fonts.cdnfonts.com/css/repetition-scrolling" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/lucide@latest" @cspNonce></script>
</head>
<body class="font-sans antialiased nativephp-safe-area">
    <div id="app">
        <character-sheet></character-sheet>
    </div>

    <script @cspNonce>
        window.lucide?.createIcons?.();
    </script>
</body>
</html>
