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

        <style>
        /* ═══════════════════════════════════════════════════════════
        THEME TOKENS
        html[data-theme="dark"]  → tactical dark
        html[data-theme="light"] → clean light
        ═══════════════════════════════════════════════════════════ */
        :root {
            --font-display: 'Bebas Neue', sans-serif;
            --font-mono:    'JetBrains Mono', monospace;
            --accent:       #0099cc;
            --accent-dim:   #006688;
            --accent-glow:  rgba(0,153,204,0.18);
            --danger:       #e02040;
            --warn:         #d4820a;
            --ok:           #0a9958;
            --header-h:     56px;
        }

        /* ── DARK THEME ── */
        [data-theme="dark"] {
            --bg-page:      #0d0f14;
            --bg-panel:     #13161e;
            --bg-card:      #181b25;
            --bg-input:     #1e2230;
            --border:       #2e3448;
            --border-hi:    #4a5278;
            --text-primary: #eceff8;
            --text-secondary:#b8c0d8;
            --text-muted:   #7e8aaa;
            --text-dim:     #4a5278;
            --shadow:       rgba(0,0,0,0.5);
            --scanline:     rgba(0,0,0,0.07);
            --ok-text:      #2edc82;
            --warn-text:    #f5aa38;
            --danger-text:  #ff4466;
        }

        /* ── LIGHT THEME ── */
        [data-theme="light"] {
            --bg-page:      #f0f2f7;
            --bg-panel:     #ffffff;
            --bg-card:      #ffffff;
            --bg-input:     #f4f6fb;
            --border:       #d0d6e8;
            --border-hi:    #a8b4d0;
            --text-primary: #1a2040;
            --text-secondary:#3a4870;
            --text-muted:   #6070a0;
            --text-dim:     #b0bcd8;
            --shadow:       rgba(0,20,60,0.1);
            --scanline:     transparent;
            --ok-text:      #0a7a44;
            --warn-text:    #b36800;
            --danger-text:  #c01530;
            --accent:       #0077aa;
            --accent-dim:   #005580;
            --accent-glow:  rgba(0,119,170,0.12);
            --ok:           #0a7a44;
            --warn:         #b36800;
            --danger:       #c01530;
        }

        /* ═══════════════════════════════════════════════════════════
        RESET & BASE
        ═══════════════════════════════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            background: var(--bg-page);
            color: var(--text-primary);
            font-family: var(--font-mono);
            font-size: 13px;
            overflow: hidden; /* prevent page scroll — grid fills viewport */
        }

        /* scanlines only in dark mode */
        body::before {
            content: '';
            position: fixed; inset: 0;
            background: repeating-linear-gradient(
                0deg, transparent, transparent 2px,
                var(--scanline) 2px, var(--scanline) 4px
            );
            pointer-events: none;
            z-index: 9000;
        }

        /* ═══════════════════════════════════════════════════════════
        HEADER
        ═══════════════════════════════════════════════════════════ */
        .site-header {
            position: fixed; top: 0; left: 0; right: 0;
            z-index: 200;
            background: var(--bg-panel);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 20px;
            height: var(--header-h);
            box-shadow: 0 2px 16px var(--shadow);
        }
        [data-theme="dark"] .site-header::after {
            content: '';
            position: absolute; bottom: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            opacity: 0.25;
        }

        .logo {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; flex-shrink: 0;
        }
        .logo-icon {
            width: 30px; height: 30px;
            border: 1.5px solid var(--accent); border-radius: 4px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 10px var(--accent-glow);
        }
        .logo-icon::before {
            content: '';
            width: 9px; height: 9px; border-radius: 50%;
            background: var(--accent); box-shadow: 0 0 6px var(--accent);
        }
        .logo-text {
            font-family: var(--font-display);
            font-size: 19px; letter-spacing: 3px;
            color: var(--text-primary);
        }
        .logo-text span { color: var(--accent); }

        .header-center {
            display: flex; align-items: center; gap: 20px;
            font-size: 11px; color: var(--text-secondary); letter-spacing: 1px;
        }
        .status-bar { display: flex; align-items: center; gap: 5px; }
        .status-count { font-weight: 700; }

        .header-meta {
            display: flex; align-items: center; gap: 18px;
        }
        .header-clock {
            font-size: 11px; color: var(--text-secondary); letter-spacing: 1px;
        }
        .rec-indicator {
            display: flex; align-items: center; gap: 5px;
            font-size: 10px; font-weight: 700; letter-spacing: 2px;
            color: var(--danger-text);
        }
        .rec-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: var(--danger-text);
            animation: blink 1.2s ease-in-out infinite;
        }
        [data-theme="dark"] .rec-dot { box-shadow: 0 0 5px var(--danger-text); }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }

        /* ── THEME TOGGLE ── */
        .theme-toggle {
            display: flex; align-items: center; gap: 8px;
            cursor: pointer; user-select: none;
            font-size: 10px; letter-spacing: 1.5px;
            color: var(--text-muted);
            padding: 5px 10px;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: var(--bg-input);
            transition: border-color 0.2s, color 0.2s;
        }
        .theme-toggle:hover { border-color: var(--accent); color: var(--accent); }
        .theme-toggle-track {
            width: 32px; height: 17px;
            background: var(--border);
            border-radius: 9px;
            position: relative;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        [data-theme="light"] .theme-toggle-track { background: var(--accent); }
        .theme-toggle-thumb {
            position: absolute; top: 2px; left: 2px;
            width: 13px; height: 13px; border-radius: 50%;
            background: var(--text-muted);
            transition: transform 0.2s, background 0.2s;
        }
        [data-theme="light"] .theme-toggle-thumb {
            transform: translateX(15px);
            background: #fff;
        }

        /* ═══════════════════════════════════════════════════════════
        LAYOUT — header + main fill exactly the viewport, no scroll
        ═══════════════════════════════════════════════════════════ */
        .main-content {
            position: fixed;
            top: var(--header-h); left: 0; right: 0; bottom: 0;
            display: flex; flex-direction: column;
            padding: 12px 16px 8px;
            overflow: hidden;
        }

        /* ═══════════════════════════════════════════════════════════
        SHARED COMPONENTS
        ═══════════════════════════════════════════════════════════ */
        .btn {
            font-family: var(--font-mono);
            font-size: 10px; font-weight: 700;
            letter-spacing: 1.5px; text-transform: uppercase;
            padding: 7px 14px; border-radius: 3px;
            cursor: pointer; transition: all 0.15s;
            text-decoration: none;
            display: inline-flex; align-items: center; gap: 7px;
            border: 1px solid var(--border);
            background: var(--bg-input);
            color: var(--text-secondary);
        }
        .btn:hover { border-color: var(--accent); color: var(--accent); box-shadow: 0 0 10px var(--accent-glow); }

        .badge {
            display: inline-flex; align-items: center; gap: 3px;
            font-size: 9px; font-weight: 700; letter-spacing: 1px;
            padding: 2px 7px; border-radius: 3px; white-space: nowrap;
        }
        .badge-online  { background: rgba(10,153,88,0.12);  color: var(--ok-text);     border: 1px solid rgba(10,153,88,0.25); }
        .badge-offline { background: rgba(192,21,48,0.12);  color: var(--danger-text); border: 1px solid rgba(192,21,48,0.25); }
        .badge-unknown { background: rgba(179,104,0,0.12);  color: var(--warn-text);   border: 1px solid rgba(179,104,0,0.25); }

        /* ─── SCROLLBAR ── */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border-hi); border-radius: 3px; }

        @yield('extra-styles')
        </style>

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

        <script>
            /* ── Clock ── */
            function updateClock() {
                const n = new Date(), p = x => String(x).padStart(2,'0');
                document.getElementById('header-clock').textContent =
                    `${p(n.getDate())}-${p(n.getMonth() + 1)}-${n.getFullYear()}  ${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
            }
            setInterval(updateClock, 1000); updateClock();

            /* ── Global status poll ── */
            async function pollStatus() {
                try {
                    const data = await fetch('/api/cameras/status').then(r => r.json());
                    const online = data.filter(c => c.online).length;
                    const el = document.getElementById('online-count');
                    el.textContent = online;
                    el.style.color = online === data.length ? 'var(--ok-text)' :
                                    online === 0            ? 'var(--danger-text)' : 'var(--warn-text)';
                } catch(e) {}
            }
            pollStatus(); setInterval(pollStatus, 30000);

            /* ── Theme toggle ── */
            function applyTheme(t) {
                document.documentElement.setAttribute('data-theme', t);
                document.getElementById('theme-label').textContent = t === 'dark' ? 'LIGHT' : 'DARK';
                localStorage.setItem('hik-theme', t);
            }
            function toggleTheme() {
                applyTheme(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
            }
            /* Apply saved theme immediately (before first paint) */
            applyTheme(localStorage.getItem('hik-theme') || 'dark');
        </script>

        @yield('scripts')
    </body>
</html>
