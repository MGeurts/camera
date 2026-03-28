@extends('layouts.app')
@section('title', 'Camera Dashboard')

@section('extra-styles')
    <style>
        /* ─── PAGE HEADER ─── */
        .page-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 10px; flex-shrink: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
        }
        .page-title {
            font-family: var(--font-display);
            font-size: 28px; letter-spacing: 4px;
            color: var(--text-primary); line-height: 1;
        }
        .page-title small {
            display: block; font-family: var(--font-mono);
            font-size: 10px; letter-spacing: 2px;
            color: var(--text-secondary); margin-top: 3px; font-weight: 400;
        }
        .page-actions { display: flex; gap: 8px; align-items: center; }

        /* ─── TOOLBAR ─── */
        .toolbar {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 10px; flex-shrink: 0; flex-wrap: wrap;
        }
        .toolbar-label {
            font-size: 10px; color: var(--text-secondary);
            letter-spacing: 1.5px; text-transform: uppercase;
        }
        .col-btn {
            background: var(--bg-input); border: 1px solid var(--border);
            color: var(--text-secondary); padding: 5px 9px; cursor: pointer;
            font-size: 10px; font-family: var(--font-mono); letter-spacing: 1px;
            border-radius: 3px; transition: all 0.15s; min-width: 30px; text-align: center;
        }
        .col-btn:hover { border-color: var(--border-hi); color: var(--text-primary); }
        .col-btn.active { border-color: var(--accent); color: var(--accent); background: var(--accent-glow); }
        .col-btn.auto-active { border-color: var(--ok-text); color: var(--ok-text); background: rgba(10,153,88,0.08); }
        .tb-divider { flex: 1; height: 1px; background: var(--border); min-width: 12px; }

        /* ── Auto-refresh toggle ── */
        .refresh-toggle { display: flex; align-items: center; gap: 7px; }
        .toggle-sw { position: relative; width: 34px; height: 18px; cursor: pointer; }
        .toggle-sw input { opacity: 0; width: 0; height: 0; }
        .toggle-track {
            position: absolute; inset: 0;
            background: var(--border); border-radius: 9px; transition: 0.2s;
        }
        .toggle-thumb {
            position: absolute; top: 2px; left: 2px;
            width: 14px; height: 14px; border-radius: 50%;
            background: var(--text-muted); transition: 0.2s;
        }
        .toggle-sw input:checked ~ .toggle-track { background: var(--accent); }
        .toggle-sw input:checked ~ .toggle-thumb { transform: translateX(16px); background: #fff; }

        /* ─── CAMERA GRID ─── */
        /*
        * Uses CSS custom property --cols, computed by JS.
        * Gap and info-bar height are also set as custom props
        * so the JS solver can read them back consistently.
        */
        .camera-grid {
            --cols: 2;
            --gap:  8px;
            display: grid;
            grid-template-columns: repeat(var(--cols), 1fr);
            gap: var(--gap);
            flex: 1;          /* stretch to fill remaining height in flex column */
            min-height: 0;
            align-content: start; /* don't stretch rows beyond their natural 16:9 size */
        }

        /* ─── CAMERA CARD ─── */
        .camera-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 5px; overflow: hidden;
            position: relative;
            display: flex; flex-direction: column;
            transition: border-color 0.18s, box-shadow 0.18s;
            cursor: pointer;
        }
        .camera-card:hover {
            border-color: var(--accent-dim);
            box-shadow: 0 0 18px var(--accent-glow);
        }
        .camera-card.offline { border-color: rgba(192,21,48,0.35); }

        /* corner brackets — only dark mode */
        [data-theme="dark"] .camera-card::before,
        [data-theme="dark"] .camera-card::after {
            content: ''; position: absolute; width: 10px; height: 10px;
            z-index: 10; pointer-events: none; opacity: 0; transition: opacity 0.2s;
        }
        [data-theme="dark"] .camera-card::before { top:5px;left:5px; border-top:1px solid var(--accent); border-left:1px solid var(--accent); }
        [data-theme="dark"] .camera-card::after  { bottom:5px;right:5px; border-bottom:1px solid var(--accent); border-right:1px solid var(--accent); }
        [data-theme="dark"] .camera-card:hover::before,
        [data-theme="dark"] .camera-card:hover::after { opacity: 1; }

        /* ─── FEED ─── */
        /*
        * aspect-ratio: 16/9 is set here so the card's feed area always
        * maintains correct proportions regardless of grid cell size.
        * The images use object-fit:contain (not cover) so the source
        * aspect ratio is always fully respected — no cropping.
        */
        .camera-feed {
            position: relative;
            background: #000;
            overflow: hidden;
            aspect-ratio: 16 / 9;
            width: 100%;
            flex-shrink: 0;
        }

        .camera-feed img {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            object-fit: contain; /* CONTAIN = source ratio always respected, never cropped */
        }

        /* HUD */
        .feed-overlay { position: absolute; inset: 0; pointer-events: none; z-index: 5; }
        .cam-id-tag {
            position: absolute; top: 6px; left: 6px;
            font-size: 8px; font-weight: 700; letter-spacing: 2px;
            color: rgba(255,255,255,0.7); background: rgba(0,0,0,0.5);
            padding: 2px 5px; border-radius: 2px;
        }
        .cam-ts-tag {
            position: absolute; bottom: 6px; left: 6px;
            font-size: 7px; letter-spacing: 1px;
            color: rgba(255,255,255,0.5); background: rgba(0,0,0,0.45);
            padding: 2px 5px; border-radius: 2px;
        }
        .expand-btn {
            position: absolute; top: 5px; right: 5px;
            width: 20px; height: 20px;
            background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.15);
            border-radius: 3px; display: flex; align-items: center; justify-content: center;
            pointer-events: all; cursor: pointer;
            opacity: 0; transition: opacity 0.18s;
            text-decoration: none; color: rgba(255,255,255,0.6); font-size: 9px;
        }
        .camera-card:hover .expand-btn { opacity: 1; }
        .expand-btn:hover { background: var(--accent-glow) !important; border-color: var(--accent) !important; color: var(--accent) !important; }

        /* Spinner — first load only */
        .feed-spinner {
            position: absolute; inset: 0; z-index: 3;
            display: flex; align-items: center; justify-content: center;
            background: var(--bg-card);
        }
        .feed-spinner.gone { display: none; }
        .spinner-ring {
            width: 22px; height: 22px;
            border: 2px solid var(--border); border-top-color: var(--accent);
            border-radius: 50%; animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ─── INFO BAR ─── */
        .camera-info {
            padding: 5px 9px;
            display: flex; align-items: center; justify-content: space-between;
            border-top: 1px solid var(--border);
            flex-shrink: 0; gap: 6px; overflow: hidden;
            background: var(--bg-card);
        }
        .cam-name {
            font-size: 10px; font-weight: 700; letter-spacing: 0.5px;
            color: var(--text-primary); text-transform: uppercase;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .cam-meta {
            font-size: 8px; color: var(--text-secondary); letter-spacing: 0.5px;
            margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        /* hide meta when grid is very dense */
        .camera-grid[data-cols="5"] .cam-meta,
        .camera-grid[data-cols="6"] .cam-meta { display: none; }

        /* ─── EMPTY STATE ─── */
        .empty-state {
            grid-column: 1/-1; padding: 80px 40px; text-align: center;
            border: 1px dashed var(--border); border-radius: 5px;
            color: var(--text-secondary);
        }
        .empty-state h2 {
            font-family: var(--font-display); font-size: 26px; letter-spacing: 4px;
            margin-bottom: 12px; color: var(--text-muted);
        }
        .empty-state p { font-size: 11px; line-height: 1.8; }
        .empty-state code {
            background: var(--bg-input); border: 1px solid var(--border);
            padding: 1px 7px; border-radius: 3px; color: var(--accent);
            font-family: var(--font-mono); font-size: 11px;
        }
    </style>
@endsection

@section('content')
    <div class="page-header" id="page-header">
        <div class="page-title">
            LIVE FEEDS
            <small>{{ $cameras->count() }} CAMERA{{ $cameras->count() !== 1 ? 'S' : '' }} CONFIGURED</small>
        </div>
        <div class="page-actions">
            <button class="btn" onclick="refreshAll()">↻ REFRESH ALL</button>
        </div>
    </div>

    <div class="toolbar" id="toolbar">
        <span class="toolbar-label">COLS:</span>
        @for ($c = 1; $c <= 6; $c++)
            <button class="col-btn" id="col-btn-{{ $c }}" onclick="setColumns({{ $c }}, true)">{{ $c }}</button>
        @endfor
        <button class="col-btn" id="col-btn-auto" onclick="setColumns('auto', true)">AUTO</button>
        <div class="tb-divider"></div>
        <div class="refresh-toggle">
            <span class="toolbar-label">REFRESH:</span>
            <label class="toggle-sw">
                <input type="checkbox" checked onchange="toggleAutoRefresh(this.checked)">
                <div class="toggle-track"></div>
                <div class="toggle-thumb"></div>
            </label>
            <span class="toolbar-label" style="color:var(--text-muted)">{{ $refresh / 1000 }}s</span>
        </div>
    </div>

    <div class="camera-grid" id="camera-grid">
        @forelse ($cameras as $camera)
            <div class="camera-card" id="card-{{ $camera['id'] }}" data-cam-id="{{ $camera['id'] }}">

                <div class="camera-feed">
                    <div class="feed-spinner" id="spinner-{{ $camera['id'] }}">
                        <div class="spinner-ring"></div>
                    </div>

                    {{-- Double-buffer: both images always in DOM, z-index swapped on load --}}
                    <img id="feed-a-{{ $camera['id'] }}"
                        src="{{ route('cameras.snapshot', $camera['id']) }}?t={{ time() }}"
                        alt="{{ $camera['name'] }}" style="z-index:2"
                        onload="onFirstLoad({{ $camera['id'] }})"/>
                    <img id="feed-b-{{ $camera['id'] }}"
                        alt="{{ $camera['name'] }}" style="z-index:1"/>

                    <div class="feed-overlay">
                        <span class="cam-id-tag">CAM {{ str_pad($camera['id'], 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="cam-ts-tag" id="ts-{{ $camera['id'] }}">--:--:--</span>
                        <a class="expand-btn" href="{{ route('cameras.show', $camera['id']) }}"
                        onclick="event.stopPropagation()">⤢</a>
                    </div>
                </div>

                <div class="camera-info">
                    <div style="overflow:hidden;min-width:0;">
                        <div class="cam-name">{{ $camera['name'] }}</div>
                        <div class="cam-meta">{{ $camera['location'] ?? '' }}{{ !empty($camera['location']) ? ' · ' : '' }}{{ $camera['ip'] }}</div>
                    </div>
                    <span class="badge badge-unknown" id="status-{{ $camera['id'] }}">● CHECK</span>
                </div>

            </div>
        @empty
            <div class="empty-state">
                <h2>NO CAMERAS CONFIGURED</h2>
                <p>Add your Hikvision cameras in <code>config/cameras.php</code><br>
                or set environment variables in your <code>.env</code> file.</p>
            </div>
        @endforelse
    </div>
@endsection

@section('scripts')
    <script>
        const CAMERAS    = @json($cameras);
        const REFRESH_MS = {{ $refresh }};
        const CAM_COUNT  = CAMERAS.length;

        /* ── Layout solver constants ── */
        const GAP      = 8;   // matches --gap CSS var (px)
        const INFO_H   = 36;  // info bar height per card (px)
        const ASPECT   = 16 / 9;

        let autoRefresh  = true;
        let refreshTimers = {};
        let userCols      = null; // null = AUTO
        const activeSlot  = {};

        /* ══════════════════════════════════════════════════════
        COLUMN SOLVER
        Tries every col count 1–6, picks the one that gives the
        largest cell while fitting all rows in the available height.
        ══════════════════════════════════════════════════════ */
        function calcOptimalCols(availW, availH, count) {
            if (count <= 0) return 1;
            const max = Math.min(count, 6);
            let bestCols = 1, bestArea = 0;

            for (let cols = 1; cols <= max; cols++) {
                const rows   = Math.ceil(count / cols);
                const cellW  = (availW - GAP * (cols - 1)) / cols;
                const cellH  = cellW / ASPECT;                        // 16:9
                const totalH = rows * (cellH + INFO_H) + (rows - 1) * GAP;

                if (totalH > availH + 2) continue;  // doesn't fit — skip

                const area = cellW * cellH;
                if (area > bestArea) { bestArea = area; bestCols = cols; }
            }
            return bestCols;
        }

        function applyColumns(cols) {
            const grid = document.getElementById('camera-grid');
            grid.style.setProperty('--cols', cols);
            grid.setAttribute('data-cols', cols);

            document.querySelectorAll('.col-btn').forEach(b => b.classList.remove('active','auto-active'));
            if (userCols === null) {
                document.getElementById('col-btn-auto')?.classList.add('auto-active');
            } else {
                document.getElementById(`col-btn-${cols}`)?.classList.add('active');
            }
        }

        function setColumns(value, save) {
            userCols = (value === 'auto') ? null : parseInt(value);
            if (save) localStorage.setItem('hik-cols', String(value));
            userCols !== null ? applyColumns(userCols) : recalc();
        }

        function recalc() {
            if (userCols !== null) return;

            const grid    = document.getElementById('camera-grid');
            const header  = document.getElementById('page-header');
            const toolbar = document.getElementById('toolbar');
            const main    = document.querySelector('.main-content');

            /* Available width = full grid width (main-content fills viewport) */
            const availW = grid.clientWidth;

            /* Available height = viewport minus fixed header minus our padding/chrome */
            const mainStyle = getComputedStyle(main);
            const padTop    = parseFloat(mainStyle.paddingTop);
            const padBot    = parseFloat(mainStyle.paddingBottom);
            const chromH    = (header?.offsetHeight ?? 0)
                            + (toolbar?.offsetHeight ?? 0)
                            + padTop + padBot + 20; /* 20 = gap between toolbar and grid */

            const availH = window.innerHeight
                        - parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-h') || '56')
                        - chromH;

            applyColumns(calcOptimalCols(availW, Math.max(availH, 120), CAM_COUNT));
        }

        /* ══════════════════════════════════════════════════════
        FLICKER-FREE DOUBLE-BUFFER (z-index swap)
        ══════════════════════════════════════════════════════ */
        function onFirstLoad(id) {
            document.getElementById(`spinner-${id}`).classList.add('gone');
            activeSlot[id] = 'a';
            updateTimestamp(id);
        }

        function refreshFeed(id) {
            const slot     = activeSlot[id] || 'a';
            const nextSlot = slot === 'a' ? 'b' : 'a';
            const front    = document.getElementById(`feed-${slot}-${id}`);
            const back     = document.getElementById(`feed-${nextSlot}-${id}`);

            back.onload = () => {
                back.style.zIndex  = '2';
                front.style.zIndex = '1';
                activeSlot[id]     = nextSlot;
                updateTimestamp(id);
            };
            back.onerror = () => {};
            back.src = `/cameras/${id}/snapshot?t=${Date.now()}`;
        }

        function refreshAll() { CAMERAS.forEach(c => refreshFeed(c.id)); }

        function startAutoRefresh() {
            CAMERAS.forEach(c => {
                clearInterval(refreshTimers[c.id]);
                if (!autoRefresh) return;
                const delay = (c.id - 1) * (REFRESH_MS / Math.max(CAMERAS.length, 1));
                setTimeout(() => {
                    refreshTimers[c.id] = setInterval(() => refreshFeed(c.id), REFRESH_MS);
                }, delay);
            });
        }

        function toggleAutoRefresh(enabled) {
            autoRefresh = enabled;
            enabled ? startAutoRefresh() : Object.values(refreshTimers).forEach(clearInterval);
        }

        /* ── Timestamp ── */
        function updateTimestamp(id) {
            const el = document.getElementById(`ts-${id}`);
            if (!el) return;
            const n = new Date(), p = x => String(x).padStart(2,'0');
            el.textContent = `${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
        }

        /* ── Status polling ── */
        function markOnline(id) {
            const el = document.getElementById(`status-${id}`);
            const card = document.getElementById(`card-${id}`);
            if (el)   { el.textContent = '● ONLINE'; el.className = 'badge badge-online'; }
            if (card) card.classList.remove('offline');
        }
        function markOffline(id) {
            const el = document.getElementById(`status-${id}`);
            const card = document.getElementById(`card-${id}`);
            if (el)   { el.textContent = '● OFFLINE'; el.className = 'badge badge-offline'; }
            if (card) card.classList.add('offline');
        }
        async function pollAllStatus() {
            try {
                const data = await fetch('/api/cameras/status').then(r => r.json());
                data.forEach(c => c.online ? markOnline(c.id) : markOffline(c.id));
            } catch(e) {}
        }

        /* ── Click to expand ── */
        document.querySelectorAll('.camera-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.closest('.expand-btn')) return;
                window.location.href = `/cameras/${this.dataset.camId}`;
            });
        });

        /* ── Resize ── */
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(recalc, 80);
        });

        /* ── Init ── */
        const savedCols = localStorage.getItem('hik-cols');
        if (savedCols) setColumns(savedCols === 'auto' ? 'auto' : parseInt(savedCols), false);
        else { userCols = null; recalc(); }

        startAutoRefresh();
        pollAllStatus();
        setInterval(pollAllStatus, 30000);
    </script>
@endsection
