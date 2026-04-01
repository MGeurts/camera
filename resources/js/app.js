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

/* ── Header camera count ─────────────────────────────────────
   On the grid page: derived from badge DOM so it always matches
   what the badges show — called by markOnline/markOffline in index.js.
   On the show page: one-shot API fetch on load (no badges to count).
   ─────────────────────────────────────────────────────────── */
function syncHeaderCount() {
    const countEl = document.getElementById('online-count');
    const totalEl = document.getElementById('total-count');
    if (!countEl || !totalEl) return;
    const total  = parseInt(totalEl.textContent) || 0;
    if (total === 0) return;
    const online = document.querySelectorAll('.badge-online').length;
    countEl.textContent = online;
    countEl.style.color = online === total ? 'var(--ok-text)'
                        : online === 0     ? 'var(--danger-text)'
                        :                    'var(--warn-text)';
}
window.syncHeaderCount = syncHeaderCount;

/* Show-page fallback: no badges exist, do a single API call */
(function () {
    if (document.querySelector('.camera-card')) return; // index.js handles it
    fetch('/api/cameras/status')
        .then(r => r.json())
        .then(data => {
            const online  = data.filter(c => c.online).length;
            const countEl = document.getElementById('online-count');
            const totalEl = document.getElementById('total-count');
            if (!countEl || !totalEl) return;
            countEl.textContent = online;
            countEl.style.color = online === data.length ? 'var(--ok-text)'
                                : online === 0           ? 'var(--danger-text)'
                                :                          'var(--warn-text)';
        })
        .catch(() => {});
})();

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

/* Sync the toggle label with whatever theme was already applied by the
   inline <script> in <head> — must run after the DOM is ready. */
applyTheme(localStorage.getItem('hik-theme') || 'dark');

/* Expose to inline onclick handlers in the Blade layout */
window.toggleTheme = toggleTheme;
