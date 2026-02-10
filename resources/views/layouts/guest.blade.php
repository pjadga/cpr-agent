<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="viewport-fit=cover, width=device-width,initial-scale=1, maximum-scale=1,user-scalable=0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="192x192" href="/favicon.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">

        <title>{{ config('app.name', 'Cyberpunk') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@300;400;500;600;700&family=Roboto+Mono:wght@400;500&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-cpr-black-deep text-cpr-text-main {{ (Auth::user()?->config?->theme ?? 'night') === 'day' ? 'theme-day' : '' }} bg-[url('{{ asset('images/external/splash-square.png') }}')] bg-cover bg-center bg-no-repeat bg-blend-overlay">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div class="w-full sm:max-w-4xl mt-6 px-6 py-4 cpr-panel shadow-2xl overflow-hidden sm:rounded-lg border-t-4 border-t-red-600">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
