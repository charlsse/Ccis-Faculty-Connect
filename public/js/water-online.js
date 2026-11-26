document.addEventListener('DOMContentLoaded', () => {
    const panel = document.querySelector('[data-online-status]');
    if (!panel || ['confirmed', 'expired', 'failed', 'attention'].includes(panel.dataset.state)) return;
    let count = 0;
    let stopped = false;
    let timer;
    const controller = new AbortController();
    const heading = panel.querySelector('[data-online-heading]');
    const message = panel.querySelector('[data-online-message]');
    const finish = () => {
        stopped = true;
        if (heading) heading.textContent = 'Payment processing';
        if (message) message.textContent = 'Confirmation is taking longer than expected. You can safely return to FacultyConnect and check your payment status later. Do not pay again yet.';
    };
    const poll = async () => {
        if (stopped) return;
        if (++count > 60) { finish(); return; }
        if (document.hidden) { timer = setTimeout(poll, 5000); return; }
        try {
            const response = await fetch(panel.dataset.onlineStatus, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store', signal: AbortSignal.any([controller.signal, AbortSignal.timeout(8000)]) });
            if (!response.ok) { finish(); return; }
            const data = await response.json();
            if (['confirmed', 'expired', 'failed', 'attention'].includes(data.status)) { stopped = true; location.reload(); return; }
        } catch (_) { if (message) message.textContent = 'Connection interrupted. Retrying status safely; do not pay again yet.'; }
        timer = setTimeout(poll, 5000);
    };
    window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); controller.abort(); }, { once: true });
    timer = setTimeout(poll, 5000);
});
