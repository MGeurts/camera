/* ─────────────────────────────────────────────────────────────
   index.js  —  camera grid page
   Data injected by index.blade.php before this file loads:
     window.HIK = { cameras: [...], refreshMs: 3000 }

   STATUS STRATEGY
   No separate /api/cameras/status poll. A camera is ONLINE when
   its image loads successfully. A camera is OFFLINE when its image fails.
   Refreshes are queued, limited and paused outside the viewport.
   Status always matches exactly what you see on screen.
   ───────────────────────────────────────────────────────────── */

const CAMERAS    = window.HIK.cameras;
let   REFRESH_MS = parseInt(localStorage.getItem('hik-refresh') || window.HIK.refreshMs);
const CAM_COUNT  = CAMERAS.length;

const GAP    = 8;
const INFO_H = 36;
const ASPECT = 16 / 9;

let autoRefresh   = true;
const refreshTimers = {};
let userCols      = null;
const activeSlot  = {};
const visibleFeeds = new Set(CAMERAS.map(camera => camera.id));
const loadingFeeds = new Set();
const queuedFeeds  = new Set();
const refreshQueue = [];
const failures     = {};
const MAX_CONCURRENT_LOADS = 4;
let activeLoads = 0;
let currentFilter = 'all';

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
    const displayedCount = document.querySelectorAll('.camera-card:not([hidden])').length || CAM_COUNT;
    applyColumns(calcOptimalCols(availW, Math.max(availH, 120), displayedCount));
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
    failures[id] = 0;
    const el   = document.getElementById(`status-${id}`);
    const card = document.getElementById(`card-${id}`);
    if (el)   { el.textContent = '● ONLINE'; el.className = 'badge badge-online'; }
    if (card) { card.classList.remove('offline'); card.dataset.status = 'online'; }
    applyFilter();
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
    if (card) { card.classList.add('offline'); card.dataset.status = 'offline'; }
    applyFilter();
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
   One load per camera is permitted at a time. The queue limits total
   concurrent image requests and each failure progressively backs off.
══════════════════════════════════════════════════════ */
function onFirstLoad(id) {
    document.getElementById(`spinner-${id}`)?.classList.add('gone');
    activeSlot[id] = 'a';
    updateTimestamp(id);
    markOnline(id);
}

function nextDelay(id) {
    const attempts = failures[id] || 0;
    return attempts ? Math.min(REFRESH_MS * (2 ** attempts), 60000) : REFRESH_MS;
}

function canRefresh(id, force = false) {
    // While viewing only incidents, continue polling the hidden cards so a
    // newly failing camera can enter the incident list without a page reload.
    return force || (autoRefresh && document.visibilityState === 'visible'
        && (visibleFeeds.has(id) || currentFilter === 'issues'));
}

function scheduleRefresh(id, delay = REFRESH_MS, force = false) {
    clearTimeout(refreshTimers[id]);
    if (!canRefresh(id, force)) return;
    refreshTimers[id] = setTimeout(() => enqueueRefresh(id, force), delay);
}

function enqueueRefresh(id, force = false) {
    if (!canRefresh(id, force) || loadingFeeds.has(id) || queuedFeeds.has(id)) return;
    queuedFeeds.add(id);
    refreshQueue.push({ id, force });
    runRefreshQueue();
}

function runRefreshQueue() {
    while (activeLoads < MAX_CONCURRENT_LOADS && refreshQueue.length) {
        const { id, force } = refreshQueue.shift();
        queuedFeeds.delete(id);
        if (!canRefresh(id, force) || loadingFeeds.has(id)) continue;
        refreshFeed(id, force);
    }
}

function refreshFeed(id, force = false) {
    if (loadingFeeds.has(id)) return;
    loadingFeeds.add(id);
    activeLoads++;
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
        finishRefresh(id, force);
    };

    back.onerror = () => {
        failures[id] = Math.min((failures[id] || 0) + 1, 6);
        markOffline(id);
        finishRefresh(id, force);
    };

    back.src = `/cameras/${id}/snapshot?t=${Date.now()}`;
}

function finishRefresh(id, force) {
    loadingFeeds.delete(id);
    activeLoads = Math.max(0, activeLoads - 1);
    // A manual refresh is a one-off. Subsequent automatic refreshes still
    // obey viewport visibility, rather than waking hidden cards forever.
    if (autoRefresh) scheduleRefresh(id, nextDelay(id));
    runRefreshQueue();
}

function refreshAll() {
    CAMERAS.forEach(c => enqueueRefresh(c.id, true));
    const btn = document.getElementById('refresh-all-btn');
    if (btn) {
        btn.textContent = '↻ REFRESHING…';
        btn.disabled    = true;
        setTimeout(() => { btn.textContent = '↻ REFRESH ALL'; btn.disabled = false; }, 500);
    }
}

function startAutoRefresh() {
    CAMERAS.forEach(c => {
        clearTimeout(refreshTimers[c.id]);
        if (!autoRefresh) return;
        const delay = Math.round(Math.random() * Math.min(REFRESH_MS, 1000));
        scheduleRefresh(c.id, delay);
    });
    updateRefreshSummary();
}

function toggleAutoRefresh(enabled) {
    autoRefresh = enabled;
    enabled ? startAutoRefresh() : Object.values(refreshTimers).forEach(clearTimeout);
    updateResetBtn();
    updateRefreshSummary();
}

const DEFAULT_REFRESH_MS = 3000;

function setRefreshRate(ms) {
    REFRESH_MS = ms;
    localStorage.setItem('hik-refresh', ms);
    if (autoRefresh) startAutoRefresh();
    updateResetBtn();
    updateRefreshSummary();
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

function updateRefreshSummary() {
    const summary = document.getElementById('refresh-summary');
    if (!summary) return;
    const label = REFRESH_MS >= 60000 ? '1 min' : `${REFRESH_MS / 1000} s`;
    summary.textContent = autoRefresh
        ? `Visible feeds · every ${label} · max ${MAX_CONCURRENT_LOADS} at once`
        : 'Auto-refresh paused';
}

function setFilter(filter) {
    currentFilter = filter;
    document.querySelectorAll('.filter-btn').forEach(button => {
        button.classList.toggle('active', button.dataset.filter === filter);
    });
    applyFilter();
}

function applyFilter() {
    const issueCount = document.querySelectorAll('.camera-card[data-status="offline"]').length;
    const issueLabel = document.getElementById('issue-count');
    if (issueLabel) issueLabel.textContent = issueCount;
    document.querySelectorAll('.camera-card').forEach(card => {
        card.hidden = currentFilter === 'issues' && card.dataset.status !== 'offline';
    });
    recalc();
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
    card.addEventListener('keydown', event => {
        if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('.expand-btn')) {
            event.preventDefault();
            window.location.href = `/cameras/${card.dataset.camId}`;
        }
    });
});

const visibilityObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        const id = parseInt(entry.target.dataset.camId);
        if (entry.isIntersecting) {
            visibleFeeds.add(id);
            if (autoRefresh) enqueueRefresh(id);
        } else {
            visibleFeeds.delete(id);
            clearTimeout(refreshTimers[id]);
        }
    });
}, { rootMargin: '160px' });
document.querySelectorAll('.camera-card').forEach(card => visibilityObserver.observe(card));

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && autoRefresh) startAutoRefresh();
    if (document.visibilityState !== 'visible') Object.values(refreshTimers).forEach(clearTimeout);
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

setFilter(currentFilter);
startAutoRefresh();
updateResetBtn();

/* Expose functions called by Blade onclick attributes */
window.setColumns        = setColumns;
window.toggleAutoRefresh = toggleAutoRefresh;
window.setRefreshRate    = val => setRefreshRate(parseInt(val));
window.resetRefreshRate  = resetRefreshRate;
window.refreshAll        = refreshAll;
window.setFilter         = setFilter;
window.enterFullscreen   = enterFullscreen;
window.exitFullscreen    = exitFullscreen;
window.onFirstLoad       = onFirstLoad;
