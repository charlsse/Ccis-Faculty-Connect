/* Reflective edge response only on the floating topbar; no perpetual animation loop. */
document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.dashboard-topbar');
    const material = header?.querySelector('[data-glass-reflection]');
    const enabled = matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)');
    let frame = 0, point;
    header?.addEventListener('pointermove', event => {
        if (!enabled.matches || !material) return;
        point = { x:event.clientX, y:event.clientY };
        if (frame) return;
        frame = requestAnimationFrame(() => {
            frame = 0;
            const rect = header.getBoundingClientRect();
            material.style.setProperty('--glass-x', `${point.x - rect.left}px`);
            material.style.setProperty('--glass-y', `${point.y - rect.top}px`);
        });
    }, {passive:true});
    header?.addEventListener('pointerleave', () => {
        cancelAnimationFrame(frame);frame=0;
        material?.style.removeProperty('--glass-x');material?.style.removeProperty('--glass-y');
    });
    window.addEventListener('pagehide', () => cancelAnimationFrame(frame));
});
