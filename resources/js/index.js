/* ─────────────────────────────────────────────────────────────
   index.js  —  camera grid page
   Data injected by index.blade.php before this file loads:
     window.HIK = { cameras: [...], refreshMs: 3000 }

   STATUS STRATEGY
   No separate /api/cameras/status poll. A camera is ONLINE when
   its image loads successfully. A camera is OFFLINE when its image
   fails and a HEAD request to the same URL also fails.
   Status always matches exactly what you see on screen.
   ───────────────────────────────────────────────────────────── */

const CAMERAS    = window.HIK.cameras;
let   REFRESH_MS = parseInt(localStorage.getItem('hik-refresh') || window.HIK.refreshMs);
const CAM_COUNT  = CAMERAS.length;

const GAP    = 8;
const INFO_H = 36;
const ASPECT = 16 / 9;

let autoRefresh   = true;
let refreshTimers = {};
let userCols      = null;
const activeSlot  = {};

/* Per-camera offline tracking */
const offlineSince = {};  // { [id]: Date }

/* ══════════════════════════════════════════════════════
   COLUMN SOLVER
══════════════════════════════════════════════════════ */
function calcOptimalCols(availW, availH, count, infoH = INFO_H) {
    if (count <= 0) return 1;
    const max = Math.min(count, 6);
    let bestCols = 1, bestArea = 0;
    for (let cols = 1; cols <= max; cols++) {
        const rows   = Math.ceil(count / cols);
        const cellW  = (availW - GAP * (cols - 1)) / cols;
        const cellH  = cellW / ASPECT;
        const totalH = rows * (cellH + infoH) + (rows - 1) * GAP;
        if (totalH > availH + 2) continue;
        const area = cellW * cellH;
        if (area > bestArea) { bestArea = area; bestCols = cols; }
    }
    return bestCols;
}

function applyColumns(cols) {
    const grid = document.getElementById('camera-grid');
    grid.style.setProperty('--cols', cols);
    grid.setAttribute('data-cols', cols);
    document.querySelectorAll('.col-btn').forEach(b => b.classList.remove('active', 'auto-active'));
    if (userCols === null) document.getElementById('col-btn-auto')?.classList.add('auto-active');
    else document.getElementById(`col-btn-${cols}`)?.classList.add('active');
}

function setColumns(value, save) {
    userCols = (value === 'auto') ? null : parseInt(value);
    if (save) localStorage.setItem('hik-cols', String(value));
    userCols !== null ? applyColumns(userCols) : recalc();
}

function recalc() {
    if (userCols !== null) return;
    const grid      = document.getElementById('camera-grid');
    const header    = document.getElementById('page-header');
    const toolbar   = document.getElementById('toolbar');
    const main      = document.querySelector('.main-content');
    const mainStyle = getComputedStyle(main);
    const chromH    = (header?.offsetHeight ?? 0) + (toolbar?.offsetHeight ?? 0)
                    + parseFloat(mainStyle.paddingTop) + parseFloat(mainStyle.paddingBottom) + 20;
    const availW    = grid.clientWidth;
    const availH    = window.innerHeight
                    - parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-h') || '56')
                    - chromH;
    applyColumns(calcOptimalCols(availW, Math.max(availH, 120), CAM_COUNT));
}

/* ══════════════════════════════════════════════════════
   STATUS BADGE HELPERS
══════════════════════════════════════════════════════ */
function fmtDuration(since) {
    const s = Math.floor((Date.now() - since.getTime()) / 1000);
    if (s < 60)   return `${s}s`;
    if (s < 3600) return `${Math.floor(s / 60)}m`;
    return `${Math.floor(s / 3600)}h`;
}

function markOnline(id) {
    delete offlineSince[id];
    const el   = document.getElementById(`status-${id}`);
    const card = document.getElementById(`card-${id}`);
    if (el)   { el.textContent = '● ONLINE'; el.className = 'badge badge-online'; }
    if (card) card.classList.remove('offline');
    if (typeof syncHeaderCount === 'function') syncHeaderCount();
}

function markOffline(id) {
    if (!offlineSince[id]) offlineSince[id] = new Date();
    const el   = document.getElementById(`status-${id}`);
    const card = document.getElementById(`card-${id}`);
    if (el) {
        el.textContent = `● OFFLINE ${fmtDuration(offlineSince[id])}`;
        el.className   = 'badge badge-offline';
    }
    if (card) card.classList.add('offline');
    if (typeof syncHeaderCount === 'function') syncHeaderCount();
}

/* Tick offline durations every 10 s so "OFFLINE 3m" stays fresh */
setInterval(() => {
    CAMERAS.forEach(c => {
        if (!offlineSince[c.id]) return;
        const el = document.getElementById(`status-${c.id}`);
        if (el) el.textContent = `● OFFLINE ${fmtDuration(offlineSince[c.id])}`;
    });
}, 10000);

/* ══════════════════════════════════════════════════════
   DOUBLE-BUFFER REFRESH
   onload  → markOnline   (image arrived = camera is up)
   onerror → HEAD confirm → markOffline if also fails
══════════════════════════════════════════════════════ */
function onFirstLoad(id) {
    document.getElementById(`spinner-${id}`)?.classList.add('gone');
    activeSlot[id] = 'a';
    updateTimestamp(id);
    markOnline(id);
}

function confirmOffline(id) {
    fetch(`/cameras/${id}/snapshot?t=${Date.now()}`, { method: 'HEAD' })
        .then(r => { if (!r.ok) markOffline(id); })
        .catch(() => markOffline(id));
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
        syncFsCell(id, back.src);
        markOnline(id);
    };

    back.onerror = () => {
        /* Only confirm via HEAD if we haven't already marked it offline —
           avoids hammering a known-dead camera with extra requests. */
        if (!offlineSince[id]) confirmOffline(id);
        else                   markOffline(id);
    };

    back.src = `/cameras/${id}/snapshot?t=${Date.now()}`;
}

function refreshAll() {
    CAMERAS.forEach(c => refreshFeed(c.id));
    const btn = document.getElementById('refresh-all-btn');
    if (btn) {
        btn.textContent = '↻ REFRESHING…';
        btn.disabled    = true;
        setTimeout(() => { btn.textContent = '↻ REFRESH ALL'; btn.disabled = false; }, 1500);
    }
}

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
    updateResetBtn();
}

const DEFAULT_REFRESH_MS = 3000;

function setRefreshRate(ms) {
    REFRESH_MS = ms;
    localStorage.setItem('hik-refresh', ms);
    if (autoRefresh) startAutoRefresh();
    updateResetBtn();
}

function updateResetBtn() {
    const btn = document.getElementById('rate-reset-btn');
    if (btn) btn.disabled = (REFRESH_MS === DEFAULT_REFRESH_MS && autoRefresh);
}

function resetRefreshRate() {
    REFRESH_MS = DEFAULT_REFRESH_MS;
    localStorage.removeItem('hik-refresh');
    const sel = document.getElementById('rate-select');
    if (sel) sel.value = DEFAULT_REFRESH_MS;
    if (!autoRefresh) {
        autoRefresh = true;
        const checkbox = document.querySelector('.toggle-sw input');
        if (checkbox) checkbox.checked = true;
    }
    startAutoRefresh();
    updateResetBtn();
}

function updateTimestamp(id) {
    const el = document.getElementById(`ts-${id}`);
    if (!el) return;
    const n = new Date(), p = x => String(x).padStart(2, '0');
    el.textContent = `${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
}

/* ── Click card to expand ── */
document.querySelectorAll('.camera-card').forEach(card => {
    card.addEventListener('click', function (e) {
        if (e.target.closest('.expand-btn')) return;
        window.location.href = `/cameras/${this.dataset.camId}`;
    });
});

/* ── Resize ── */
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => { recalc(); if (fsActive) layoutFsGrid(); }, 80);
});

/* ══════════════════════════════════════════════════════
   FULLSCREEN  — solid HUD bar + visible quit button
══════════════════════════════════════════════════════ */
let fsActive    = false;
let fsHideTimer = null;
let fsHudTimer  = null;
const fsSlot    = {};

function buildFsGrid() {
    const grid = document.getElementById('fs-grid');
    grid.innerHTML = '';
    CAMERAS.forEach(cam => {
        fsSlot[cam.id] = 'a';
        const currentSrc = (activeSlot[cam.id] === 'a'
            ? document.getElementById(`feed-a-${cam.id}`)
            : document.getElementById(`feed-b-${cam.id}`))?.src ?? '';
        const cell = document.createElement('div');
        cell.className = 'fs-cell';
        cell.id = `fs-cell-${cam.id}`;
        cell.innerHTML = `
            <img id="fs-a-${cam.id}" src="${currentSrc}" style="z-index:2;position:absolute;inset:0;width:100%;height:100%;object-fit:contain;" />
            <img id="fs-b-${cam.id}"                     style="z-index:1;position:absolute;inset:0;width:100%;height:100%;object-fit:contain;" />
            <span class="fs-label">${cam.name}</span>`;
        grid.appendChild(cell);
    });
}

function layoutFsGrid() {
    const grid = document.getElementById('fs-grid');
    /* Respect the user-selected column count (1-6).
       Only auto-calculate when the toolbar is set to AUTO (userCols === null). */
    if (userCols !== null) {
        grid.style.setProperty('--cols', userCols);
        return;
    }
    const availW = window.innerWidth  - GAP * 2;
    const availH = window.innerHeight - GAP * 2 - 40; /* 40px HUD bar */
    const cols   = calcOptimalCols(availW, availH, CAM_COUNT, 0);
    grid.style.setProperty('--cols', cols);
}

function syncFsCell(id, src) {
    if (!fsActive) return;
    const slot     = fsSlot[id] || 'a';
    const nextSlot = slot === 'a' ? 'b' : 'a';
    const front    = document.getElementById(`fs-${slot}-${id}`);
    const back     = document.getElementById(`fs-${nextSlot}-${id}`);
    if (!front || !back) return;
    back.onload = () => {
        back.style.zIndex  = '2';
        front.style.zIndex = '1';
        fsSlot[id] = nextSlot;
    };
    back.onerror = () => {};
    back.src = src;
}

function updateFsHud() {
    const clock   = document.getElementById('fs-hud-clock');
    const countEl = document.getElementById('fs-hud-count');
    if (clock) {
        const n = new Date(), p = x => String(x).padStart(2, '0');
        clock.textContent = `${p(n.getDate())}-${p(n.getMonth()+1)}-${n.getFullYear()}  ${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
    }
    if (countEl) {
        const onlineEl = document.getElementById('online-count');
        const totalEl  = document.getElementById('total-count');
        if (onlineEl && totalEl) {
            countEl.textContent = `${onlineEl.textContent} / ${totalEl.textContent} ONLINE`;
            countEl.style.color = onlineEl.style.color;
        }
    }
}

function showQuitBtn() {
    const btn = document.getElementById('fs-quit');
    if (!btn) return;
    btn.classList.add('fs-quit-visible');
    clearTimeout(fsHideTimer);
    fsHideTimer = setTimeout(() => btn.classList.remove('fs-quit-visible'), 2500);
}

function enterFullscreen() {
    buildFsGrid();
    layoutFsGrid();
    document.getElementById('fs-overlay').style.display = 'flex';
    document.body.classList.add('fs-active');
    fsActive = true;
    const el = document.documentElement;
    if (el.requestFullscreen)            el.requestFullscreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    updateFsHud();
    fsHudTimer = setInterval(updateFsHud, 1000);
    showQuitBtn();
    document.getElementById('fs-overlay').addEventListener('mousemove', showQuitBtn);
}

function exitFullscreen() {
    fsActive = false;
    clearInterval(fsHudTimer);
    document.getElementById('fs-overlay').style.display = 'none';
    document.getElementById('fs-quit')?.classList.remove('fs-quit-visible');
    document.body.classList.remove('fs-active');
    if (document.fullscreenElement || document.webkitFullscreenElement) {
        if (document.exitFullscreen)            document.exitFullscreen();
        else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
    }
}

document.addEventListener('fullscreenchange',       onFsChange);
document.addEventListener('webkitfullscreenchange', onFsChange);
function onFsChange() {
    if (!document.fullscreenElement && !document.webkitFullscreenElement && fsActive) {
        exitFullscreen();
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && fsActive) exitFullscreen();
});

/* ══════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════ */
const savedCols = localStorage.getItem('hik-cols');
if (savedCols) setColumns(savedCols === 'auto' ? 'auto' : parseInt(savedCols), false);
else { userCols = null; recalc(); }

const savedRate = localStorage.getItem('hik-refresh');
if (savedRate) {
    REFRESH_MS = parseInt(savedRate);
    const sel = document.getElementById('rate-select');
    if (sel) sel.value = savedRate;
}

startAutoRefresh();
updateResetBtn();

/* Expose functions called by Blade onclick attributes */
window.setColumns        = setColumns;
window.toggleAutoRefresh = toggleAutoRefresh;
window.setRefreshRate    = val => setRefreshRate(parseInt(val));
window.resetRefreshRate  = resetRefreshRate;
window.refreshAll        = refreshAll;
window.enterFullscreen   = enterFullscreen;
window.exitFullscreen    = exitFullscreen;
window.onFirstLoad       = onFirstLoad;
