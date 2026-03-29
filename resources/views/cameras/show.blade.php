@extends('layouts.app')
@section('title', $camera['name'] . ' — HIKVISION')

@section('extra-styles')
    <style>
        .back-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            flex-shrink: 0;
        }

        .back-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.15s;
        }

        .back-link:hover {
            color: var(--accent);
        }

        .breadcrumb {
            font-size: 10px;
            color: var(--text-muted);
            letter-spacing: 1px;
        }

        .breadcrumb span {
            color: var(--text-secondary);
        }

        /* ── single camera layout ── */
        .single-layout {
            display: grid;
            grid-template-columns: 1fr 270px;
            gap: 14px;
            flex: 1;
            min-height: 0;
            align-items: start;
        }

        @media(max-width:860px) {
            .single-layout {
                grid-template-columns: 1fr;
            }
        }

        /* ── Feed ── */
        .feed-wrap {
            position: relative;
            background: #000;
            border: 1px solid var(--border);
            border-radius: 5px;
            overflow: hidden;
        }

        [data-theme="dark"] .feed-wrap::before,
        [data-theme="dark"] .feed-wrap::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            z-index: 10;
            pointer-events: none;
        }

        [data-theme="dark"] .feed-wrap::before {
            top: 7px;
            left: 7px;
            border-top: 2px solid var(--accent);
            border-left: 2px solid var(--accent);
            box-shadow: -2px -2px 10px var(--accent-glow);
        }

        [data-theme="dark"] .feed-wrap::after {
            bottom: 7px;
            right: 7px;
            border-bottom: 2px solid var(--accent);
            border-right: 2px solid var(--accent);
            box-shadow: 2px 2px 10px var(--accent-glow);
        }

        /*
     * CONTAIN: the image is never cropped — source aspect ratio always respected.
     * Black bars appear if the source ratio differs from 16:9.
     */
        .feed-img {
            display: block;
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: contain;
            background: #000;
        }

        .feed-hud {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 5;
        }

        .hud-tag {
            position: absolute;
            top: 10px;
            left: 10px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, 0.75);
            background: rgba(0, 0, 0, 0.55);
            padding: 3px 8px;
            border-radius: 2px;
        }

        .hud-ts {
            position: absolute;
            bottom: 10px;
            left: 10px;
            font-size: 9px;
            color: rgba(255, 255, 255, 0.5);
            background: rgba(0, 0, 0, 0.5);
            padding: 3px 8px;
            border-radius: 2px;
            letter-spacing: 1px;
        }

        /* refresh progress bar */
        .refresh-bar {
            height: 2px;
            background: var(--border);
            position: relative;
            overflow: hidden;
        }

        .refresh-bar-fill {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: var(--accent);
            width: 0;
            box-shadow: 0 0 6px var(--accent);
            transition: none;
        }

        /* ── Sidebar ── */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: calc(100vh - var(--header-h) - 90px);
            overflow-y: auto;
        }

        .info-panel {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 5px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .panel-header {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2.5px;
            color: var(--text-secondary);
            text-transform: uppercase;
            background: var(--bg-input);
        }

        .panel-body {
            padding: 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 7px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 10px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-secondary);
            letter-spacing: 0.5px;
            flex-shrink: 0;
            margin-right: 12px;
        }

        .info-value {
            color: var(--text-primary);
            text-align: right;
            word-break: break-all;
            font-weight: 500;
        }

        .info-value.accent {
            color: var(--accent);
        }

        .info-value.ok {
            color: var(--ok-text);
        }

        .info-value.danger {
            color: var(--danger-text);
        }

        .controls-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
            padding: 10px;
        }

        .ctrl-btn {
            background: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 9px;
            border-radius: 3px;
            cursor: pointer;
            font-family: var(--font-mono);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.15s;
            text-align: center;
        }

        .ctrl-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-glow);
        }

        .ctrl-btn:active {
            transform: scale(0.97);
        }

        .spinner-inline {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 6px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .ctrl-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
            border-color: var(--border) !important;
            color: var(--text-muted) !important;
            background: var(--bg-input) !important;
            box-shadow: none !important;
        }
    </style>
@endsection

@section('content')

    <div class="back-nav">
        <a class="back-link" href="{{ route('cameras.index') }}">← GRID</a>
        <span class="breadcrumb">/ <span>{{ strtoupper($camera['name']) }}</span></span>
    </div>

    <div class="single-layout">

        {{-- Feed --}}
        <div style="display:flex;flex-direction:column;gap:0;">
            <div class="feed-wrap">
                <img class="feed-img" id="feed-a" src="{{ route('cameras.snapshot', $camera['id']) }}?t={{ time() }}" alt="{{ $camera['name'] }}" style="z-index:2; position:relative;" />
                <img class="feed-img" id="feed-b" alt="{{ $camera['name'] }}" style="z-index:1; position:absolute; inset:0; width:100%; height:100%;" />
                <div class="feed-hud">
                    <span class="hud-tag">CAM {{ str_pad($camera['id'], 2, '0', STR_PAD_LEFT) }} · {{ strtoupper($camera['name']) }}</span>
                    <span class="hud-ts" id="main-ts">--:--:--</span>
                </div>
            </div>
            <div class="refresh-bar">
                <div class="refresh-bar-fill" id="refresh-bar"></div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="sidebar">

            <div class="info-panel">
                <div class="panel-header">STREAM</div>
                <div class="panel-body">
                    <div class="info-row">
                        <span class="info-label">Method</span>
                        <span class="info-value accent">JPEG Snapshot</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Refresh</span>
                        <span class="info-value" id="refresh-rate-label">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last frame</span>
                        <span class="info-value" id="last-frame">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status</span>
                        <span class="info-value ok" id="stream-status">LOADING</span>
                    </div>
                </div>
            </div>

            <div class="info-panel">
                <div class="panel-header">CAMERA</div>
                <div class="panel-body">
                    <div class="info-row"><span class="info-label">Name</span><span class="info-value">{{ $camera['name'] }}</span></div>
                    <div class="info-row"><span class="info-label">IP</span><span class="info-value accent">{{ $camera['ip'] }}</span></div>
                    <div class="info-row"><span class="info-label">HTTP Port</span><span class="info-value">{{ $camera['http_port'] }}</span></div>
                    <div class="info-row"><span class="info-label">RTSP Port</span><span class="info-value">{{ $camera['rtsp_port'] }}</span></div>
                    <div class="info-row"><span class="info-label">Channel</span><span class="info-value">{{ $camera['channel'] }}</span></div>
                    <div class="info-row"><span class="info-label">Stream</span><span class="info-value">{{ strtoupper($camera['stream'] ?? 'SUB') }}</span></div>
                    @if(!empty($camera['location']))
                        <div class="info-row"><span class="info-label">Location</span><span class="info-value">{{ $camera['location'] }}</span></div>
                    @endif
                </div>
            </div>

            <div class="info-panel" id="device-panel" style="display:none">
                <div class="panel-header">DEVICE INFO</div>
                <div class="panel-body" id="device-body"></div>
            </div>

            <div class="info-panel">
                <div class="panel-header">CONTROLS</div>
                <div class="controls-grid">
                    <button class="ctrl-btn" onclick="refreshNow()">↻ Refresh</button>
                    <button class="ctrl-btn" id="save-btn" onclick="saveSnapshot()">⤓ Save</button>
                    <button class="ctrl-btn" onclick="togglePause()" id="pause-btn">⏸ Pause</button>
                    <button class="ctrl-btn" onclick="loadDeviceInfo()">ℹ Device</button>
                </div>
            </div>

        </div>
    </div>

@endsection

@section('scripts')
    <script>
        const CAM_ID = {{ $camera['id'] }};
        let REFRESH_MS = parseInt(localStorage.getItem('hik-refresh') || '{{ $refresh }}');

        let paused = false;
        let refreshTimer = null;
        let barTimer = null;
        let barStart = null;
        let activeSlot = 'a';

        /* ── Double-buffer swap ── */
        function swapBuffer(src) {
            const nextSlot = activeSlot === 'a' ? 'b' : 'a';
            const front = document.getElementById(`feed-${activeSlot}`);
            const back = document.getElementById(`feed-${nextSlot}`);

            back.onload = () => {
                back.style.zIndex = '2';
                back.style.position = 'relative';
                front.style.zIndex = '1';
                front.style.position = 'absolute';
                front.style.inset = '0';
                front.style.width = '100%';
                front.style.height = '100%';
                activeSlot = nextSlot;
                updateTs();
                document.getElementById('stream-status').textContent = 'LIVE';
                document.getElementById('stream-status').className = 'info-value ok';
                document.getElementById('save-btn').disabled = false;
            };
            back.onerror = () => {
                document.getElementById('stream-status').textContent = 'ERROR';
                document.getElementById('stream-status').className = 'info-value danger';
                document.getElementById('save-btn').disabled = true;
            };
            back.src = src;
        }

        function refreshNow() {
            swapBuffer(`/cameras/${CAM_ID}/snapshot?t=${Date.now()}`);
            resetBar();
        }

        function updateTs() {
            const n = new Date(), p = x => String(x).padStart(2, '0');
            const ts = `${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
            document.getElementById('main-ts').textContent = ts;
            document.getElementById('last-frame').textContent = ts;
        }

        /* ── Progress bar ── */
        function resetBar() {
            clearInterval(barTimer);
            const bar = document.getElementById('refresh-bar');
            bar.style.width = '0%';
            barStart = Date.now();
            barTimer = setInterval(() => {
                const pct = Math.min(((Date.now() - barStart) / REFRESH_MS) * 100, 100);
                bar.style.width = pct + '%';
            }, 50);
        }

        function startRefresh() {
            clearInterval(refreshTimer);
            refreshTimer = setInterval(refreshNow, REFRESH_MS);
            resetBar();
        }

        function togglePause() {
            paused = !paused;
            const btn = document.getElementById('pause-btn');
            if (paused) {
                clearInterval(refreshTimer); clearInterval(barTimer);
                btn.textContent = '▶ Resume';
                btn.style.borderColor = 'var(--warn-text)';
                btn.style.color = 'var(--warn-text)';
            } else {
                btn.textContent = '⏸ Pause';
                btn.style.borderColor = '';
                btn.style.color = '';
                startRefresh();
            }
        }

        function saveSnapshot() {
            const a = document.createElement('a');
            a.href = `/cameras/${CAM_ID}/snapshot?t=${Date.now()}`;
            a.download = `cam-${CAM_ID}-${Date.now()}.jpg`;
            a.click();
        }

        async function loadDeviceInfo() {
            const panel = document.getElementById('device-panel');
            const body = document.getElementById('device-body');
            panel.style.display = 'block';
            body.innerHTML = `<div class="info-row" style="padding:10px 12px;">
            <span class="spinner-inline"></span>
            <span style="color:var(--text-secondary);font-size:10px">Contacting camera…</span>
        </div>`;

            try {
                const data = await fetch(`/api/cameras/${CAM_ID}/info`).then(r => r.json());

                if (data.success) {
                    // Success — show the device fields (skip the 'success' key itself)
                    const skip = new Set(['success']);
                    const labels = { model: 'Model', serial: 'Serial No.', firmware: 'Firmware', device_name: 'Device Name' };
                    body.innerHTML = Object.entries(data)
                        .filter(([k]) => !skip.has(k))
                        .map(([k, v]) => `
                        <div class="info-row">
                            <span class="info-label">${labels[k] ?? k.replace(/_/g, ' ')}</span>
                            <span class="info-value">${v}</span>
                        </div>`)
                        .join('');
                } else {
                    // Failure — show reason + fix hints
                    const hints = {
                        401: ['Verify the username and password in <code>config/cameras.php</code> or <code>.env</code>.',
                            'Try logging into the camera web UI with the same credentials.'],
                        403: ['Open the camera web UI → Configuration → User Management.',
                            'Edit the user and enable <strong>Remote: Live View</strong> permission.'],
                        404: ['Your camera firmware may not expose the ISAPI device info endpoint.',
                            'This is cosmetic only — snapshots will still work normally.'],
                        0: ['Check that port <code>{{ $camera["http_port"] }}</code> is open on the camera.',
                            'Ensure the server and camera are on the same network segment.'],
                    };
                    const statusHints = hints[data.status] ?? [`HTTP status: ${data.status}`];
                    body.innerHTML = `
                    <div style="padding:10px 12px; border-bottom:1px solid var(--border);">
                        <div style="color:var(--danger-text);font-size:10px;font-weight:700;margin-bottom:6px;">
                            ✗ ${data.reason ?? 'Could not retrieve device info'}
                        </div>
                        <div style="color:var(--text-secondary);font-size:10px;line-height:1.7;">
                            ${statusHints.map(h => `<div>→ ${h}</div>`).join('')}
                        </div>
                    </div>
                    <div style="padding:8px 12px;">
                        <span style="font-size:9px;color:var(--text-muted);letter-spacing:1px;">
                            NOTE: Snapshots use a different endpoint and may still work even if this fails.
                        </span>
                    </div>`;
                }
            } catch (e) {
                body.innerHTML = `<div style="padding:10px 12px;color:var(--danger-text);font-size:10px;">
                ✗ Request failed — ${e.message}
            </div>`;
            }
        }

        /* ── Init ── */
        document.getElementById('save-btn').disabled = true; // enabled on first successful frame
        document.getElementById('refresh-rate-label').textContent =
            REFRESH_MS >= 60000 ? '1m' : (REFRESH_MS / 1000) + 's';
        startRefresh();
    </script>
@endsection
