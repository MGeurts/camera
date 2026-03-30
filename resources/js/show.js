/*
 * show.js — Single camera view logic
 *
 * Config is injected by show.blade.php via window.HIK before this file loads:
 *
 *   window.HIK = {
 *       camId:      1,       // camera ID
 *       refreshMs:  3000,    // default from config
 *       httpPort:   80,      // camera HTTP port (used in error hints)
 *   };
 */

const CAM_ID     = window.HIK.camId;
let   REFRESH_MS = parseInt(localStorage.getItem('hik-refresh') || window.HIK.refreshMs);

let paused      = false;
let refreshTimer = null;
let barTimer    = null;
let barStart    = null;
let activeSlot  = 'a';

/* ── Double-buffer swap ── */
function swapBuffer(src) {
    const nextSlot = activeSlot === 'a' ? 'b' : 'a';
    const front    = document.getElementById(`feed-${activeSlot}`);
    const back     = document.getElementById(`feed-${nextSlot}`);

    back.onload = () => {
        back.style.zIndex    = '2';
        back.style.position  = 'relative';
        front.style.zIndex   = '1';
        front.style.position = 'absolute';
        front.style.inset    = '0';
        front.style.width    = '100%';
        front.style.height   = '100%';
        activeSlot = nextSlot;
        updateTs();
        document.getElementById('stream-status').textContent = 'LIVE';
        document.getElementById('stream-status').className   = 'info-value ok';
        document.getElementById('save-btn').disabled = false;
    };
    back.onerror = () => {
        document.getElementById('stream-status').textContent = 'ERROR';
        document.getElementById('stream-status').className   = 'info-value danger';
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
    document.getElementById('main-ts').textContent     = ts;
    document.getElementById('last-frame').textContent  = ts;
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
        btn.textContent        = '▶ Resume';
        btn.style.borderColor  = 'var(--warn-text)';
        btn.style.color        = 'var(--warn-text)';
    } else {
        btn.textContent        = '⏸ Pause';
        btn.style.borderColor  = '';
        btn.style.color        = '';
        startRefresh();
    }
}

function saveSnapshot() {
    const a      = document.createElement('a');
    a.href       = `/cameras/${CAM_ID}/snapshot?t=${Date.now()}`;
    a.download   = `cam-${CAM_ID}-${Date.now()}.jpg`;
    a.click();
}

async function loadDeviceInfo() {
    const panel = document.getElementById('device-panel');
    const body  = document.getElementById('device-body');
    panel.style.display = 'block';
    body.innerHTML = `<div class="info-row" style="padding:10px 12px;">
        <span class="spinner-inline"></span>
        <span style="color:var(--text-secondary);font-size:10px">Contacting camera…</span>
    </div>`;

    try {
        const data = await fetch(`/api/cameras/${CAM_ID}/info`).then(r => r.json());

        if (data.success) {
            const skip   = new Set(['success']);
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
            const hints = {
                401: ['Verify the username and password in <code>config/cameras.php</code> or <code>.env</code>.',
                      'Try logging into the camera web UI with the same credentials.'],
                403: ['Open the camera web UI → Configuration → User Management.',
                      'Edit the user and enable <strong>Remote: Live View</strong> permission.'],
                404: ['Your camera firmware may not expose the ISAPI device info endpoint.',
                      'This is cosmetic only — snapshots will still work normally.'],
                0:   [`Check that port <code>${window.HIK.httpPort}</code> is open on the camera.`,
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
    } catch(e) {
        body.innerHTML = `<div style="padding:10px 12px;color:var(--danger-text);font-size:10px;">
            ✗ Request failed — ${e.message}
        </div>`;
    }
}

/* ── Init ── */
document.getElementById('save-btn').disabled = true;
document.getElementById('refresh-rate-label').textContent = REFRESH_MS >= 60000 ? '1m' : (REFRESH_MS / 1000) + 's';
startRefresh();
