<!DOCTYPE html>
<html lang="en" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HIKVISION — SURVEILLANCE DASHBOARD')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=JetBrains+Mono:wght@300;400;500;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @yield('head')
</head>

<body>
    <header class="site-header">
        <a class="logo" href="{{ route('cameras.index') }}">
            <div class="logo-icon"></div>
            <span class="logo-text">HIK<span>VISION</span></span>
        </a>

        <div class="header-center">
            <div class="status-bar" id="global-status">
                <span>CAMERAS</span>
                <span class="status-count" id="online-count" style="color:var(--ok-text)">—</span>
                <span style="color:var(--text-muted)">/</span>
                <span id="total-count">{{ config('cameras.cameras') ? count(array_filter(config('cameras.cameras'), fn($c) => $c['enabled'] ?? true)) : 0 }}</span>
                <span style="color:var(--text-muted)">ONLINE</span>
            </div>
            <div class="header-clock" id="header-clock">----/--/-- --:--:--</div>
            <div class="rec-indicator">
                <div class="rec-dot"></div>LIVE
            </div>
        </div>

        <div class="header-meta">
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle light/dark theme">
                <span id="theme-label">LIGHT</span>
                <div class="theme-toggle-track">
                    <div class="theme-toggle-thumb"></div>
                </div>
            </button>
        </div>
    </header>

    <main class="main-content">
        @yield('content')
    </main>

    @yield('scripts')
</body>

</html>
