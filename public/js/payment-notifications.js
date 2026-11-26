document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('[data-notification-feed]');
    if (!menu) return;
    let count = 0, timer, stopped = false, revision;
    const controller = new AbortController();
    const poll = async () => {
        if (stopped || ++count > 60) return;
        if (!document.hidden) {
            try {
                const response = await fetch(menu.dataset.notificationFeed, { credentials: 'same-origin', cache: 'no-store',
                    headers: { Accept: 'application/json' }, signal: AbortSignal.any([controller.signal, AbortSignal.timeout(8000)]) });
                if ([401, 403, 419].includes(response.status)) return;
                if (!response.ok) throw new Error('Unavailable');
                const data = await response.json();
                const badge = menu.querySelector('[data-unread-count]');
                badge.hidden = data.unread === 0;
                badge.textContent = data.unread > 9 ? '9+' : String(data.unread);
                menu.querySelector('[data-notification-toggle]').setAttribute('aria-label', `Notifications, ${data.unread} unread`);
                menu.querySelector('[data-read-all]').hidden = data.unread === 0;
                const focused = menu.contains(document.activeElement);
                if (!focused && data.revision !== revision) menu.querySelector('[data-notification-list]').innerHTML = data.html;
                if (revision && revision !== data.revision && menu.dataset.paymentAutoRefresh === 'true'
                    && !focused && !document.querySelector('input:focus,select:focus,textarea:focus,form[data-dirty]')) {
                    stopped = true;
                    location.reload();
                    return;
                }
                if (!focused) revision = data.revision;
            } catch (_) { /* Retry transient failures without discarding the rendered notifications. */ }
        }
        timer = setTimeout(poll, 15000);
    };
    // Never discard an in-progress form when new payment activity arrives.
    document.addEventListener('input', event => { if (event.target.form) event.target.form.dataset.dirty = 'true'; });
    window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); controller.abort(); }, { once: true });
    poll();
});
