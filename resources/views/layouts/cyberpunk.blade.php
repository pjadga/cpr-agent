<!DOCTYPE html>
<html class="theme-{{ Auth::user()?->config?->theme ?? 'night' }}"
      lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="viewport-fit=cover, width=device-width, initial-scale=1, maximum-scale=1,user-scalable=0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
    @cspMetaTag

    <title>{{ config('app.name', 'Cyberpunk Interface') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/hack-font@3/build/web/hack.css">
    <link href="https://fonts.cdnfonts.com/css/repetition-scrolling" rel="stylesheet">

    <!-- Scripts -->
    @include('partials.reverb-config')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://unpkg.com/lucide@latest" @cspNonce></script>
    <script @cspNonce>
        window.AgentConfig = {
            channel_id: '{{ Auth::user()?->channel_id }}',
        };
    </script>
</head>
<body class="font-sans antialiased nativephp-safe-area">
@yield('content')

<script @cspNonce>
    // Initialize Lucide icons if they are used in Blade templates
    lucide.createIcons();
</script>
</body>
</html>
