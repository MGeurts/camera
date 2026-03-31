/* ─────────────────────────────────────────────────────────────
   app.js  —  shared across every page
   ───────────────────────────────────────────────────────────── */

/* ── Clock ── */
function updateClock() {
    const n = new Date(), p = x => String(x).padStart(2, '0');
    const el = document.getElementById('header-clock');
    if (el) el.textContent =
        `${p(n.getDate())}-${p(n.getMonth() + 1)}-${n.getFullYear()}  ${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
}
setInterval(updateClock, 1000);
updateClock();

/* ── Global camera status poll ── */
async function pollStatus() {
    try {
        const data = await fetch('/api/cameras/status').then(r => r.json());
        const online = data.filter(c => c.online).length;
        const el = document.getElementById('online-count');
        if (!el) return;
        el.textContent  = online;
        el.style.color  = online === data.length ? 'var(--ok-text)'
                        : online === 0           ? 'var(--danger-text)'
                        :                          'var(--warn-text)';
    } catch (_) {}
}
pollStatus();
setInterval(pollStatus, 30000);

/* ── Theme toggle ── */
function applyTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    const label = document.getElementById('theme-label');
    if (label) label.textContent = t === 'dark' ? 'LIGHT' : 'DARK';
    localStorage.setItem('hik-theme', t);
}

function toggleTheme() {
    applyTheme(
        document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'
    );
}

/* Apply saved theme immediately */
applyTheme(localStorage.getItem('hik-theme') || 'dark');

/* Expose to inline onclick handlers in the Blade layout */
window.toggleTheme = toggleTheme;
