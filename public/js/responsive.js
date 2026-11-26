document.addEventListener('DOMContentLoaded', () => {
    const root = document.documentElement;
    let frame;
    const syncViewport = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            root.style.setProperty('--fc-visible-height', `${window.visualViewport?.height || innerHeight}px`);
            root.style.setProperty('--fc-viewport-top', `${Math.max(0, window.visualViewport?.offsetTop || 0)}px`);
        });
    };
    syncViewport();
    window.visualViewport?.addEventListener('resize', syncViewport, {passive:true});
    window.visualViewport?.addEventListener('scroll', syncViewport, {passive:true});
    window.addEventListener('resize', syncViewport, {passive:true});
    document.querySelectorAll('.table-wrap,.water-table-scroll,.water-table-wrap,.wallet-table').forEach(region => {
        if (!region.querySelector('table')) return;
        if (!region.hasAttribute('tabindex')) region.tabIndex = 0;
        if (!region.hasAttribute('role')) region.setAttribute('role', 'region');
        if (!region.hasAttribute('aria-label')) region.setAttribute('aria-label', 'Data table; scroll horizontally for more columns');
    });
    document.querySelectorAll('.payment-primary,.payment-secondary').forEach(nav => {
        const active = nav.querySelector('[aria-current]');
        if (!active || nav.scrollWidth <= nav.clientWidth) return;
        nav.scrollLeft = Math.max(0, active.offsetLeft - nav.offsetLeft - (nav.clientWidth - active.offsetWidth) / 2);
    });
    window.addEventListener('pagehide', () => cancelAnimationFrame(frame), {once:true});
});
