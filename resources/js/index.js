/* ─────────────────────────────────────────────────────────────
   index.js  —  camera grid page
   Data injected by index.blade.php before this file loads:
     window.HIK = { cameras: [...], refreshMs: 3000 }
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
   DOUBLE-BUFFER REFRESH
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
        syncFsCell(id, back.src);
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
    // Re-enable auto-refresh if it was paused
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

/* ── Status polling ── */
function markOnline(id) {
    const el   = document.getElementById(`status-${id}`);
    const card = document.getElementById(`card-${id}`);
    if (el)   { el.textContent = '● ONLINE'; el.className = 'badge badge-online'; }
    if (card) card.classList.remove('offline');
}
function markOffline(id) {
    const el   = document.getElementById(`status-${id}`);
    const card = document.getElementById(`card-${id}`);
    if (el)   { el.textContent = '● OFFLINE'; el.className = 'badge badge-offline'; }
    if (card) card.classList.add('offline');
}
async function pollAllStatus() {
    try {
        const data = await fetch('/api/cameras/status').then(r => r.json());
        data.forEach(c => c.online ? markOnline(c.id) : markOffline(c.id));
    } catch (_) {}
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
   FULLSCREEN
══════════════════════════════════════════════════════ */
let fsActive    = false;
let fsHideTimer = null;
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
    const grid   = document.getElementById('fs-grid');
    const availW = window.innerWidth  - GAP * 2;
    const availH = window.innerHeight - GAP * 2;
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

function showQuitBtn() {
    const btn = document.getElementById('fs-quit');
    btn.style.opacity = '1';
    clearTimeout(fsHideTimer);
    fsHideTimer = setTimeout(() => { btn.style.opacity = '0'; }, 2500);
}

function enterFullscreen() {
    buildFsGrid();
    layoutFsGrid();
    const overlay = document.getElementById('fs-overlay');
    const quitBtn = document.getElementById('fs-quit');
    overlay.style.display = 'flex';
    quitBtn.style.display = 'block';
    document.body.classList.add('fs-active');
    fsActive = true;
    const el = document.documentElement;
    if (el.requestFullscreen)            el.requestFullscreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    showQuitBtn();
    overlay.addEventListener('mousemove', showQuitBtn);
}

function exitFullscreen() {
    fsActive = false;
    document.getElementById('fs-overlay').style.display = 'none';
    const qb = document.getElementById('fs-quit');
    qb.style.opacity = '0';
    qb.style.display = 'none';
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
pollAllStatus();
setInterval(pollAllStatus, 30000);
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
