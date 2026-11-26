/* Stable grid + coherent illumination. No moving particles or external library. */
(() => {
    'use strict';
    const smooth = (a, b, x) => { const t = Math.max(0, Math.min(1, (x - a) / (b - a))); return t * t * (3 - 2 * t); };
    const boot = () => {
        const reduced = matchMedia('(prefers-reduced-motion: reduce)');
        const compact = matchMedia('(max-width: 760px)');
        const effects = new Map();
        const listeners = new AbortController();
        let frame = 0, last = 0, disposed = false;
        const wake = () => {
            if (!frame && !disposed && !document.hidden && !reduced.matches && [...effects.values()].some(effect => effect.visible)) frame = requestAnimationFrame(tick);
        };
        const tick = now => {
            frame = 0;
            if (disposed || document.hidden || reduced.matches) { last = 0; return; }
            const interval = compact.matches ? 1000 / 24 : 1000 / 30;
            if (!last || now - last >= interval) {
                const delta = last ? Math.min((now - last) / 1000, .1) : 0;
                last = now;
                effects.forEach(effect => { if (effect.visible) { effect.time += delta; effect.draw(); } });
            }
            wake();
        };
        const intersection = 'IntersectionObserver' in window ? new IntersectionObserver(entries => {
            entries.forEach(entry => {
                const effect = effects.get(entry.target);
                if (effect) { effect.visible = entry.isIntersecting; entry.target.dataset.dotState = entry.isIntersecting ? (reduced.matches ? 'static' : 'running') : 'paused'; }
            });
            if (![...effects.values()].some(effect => effect.visible)) { cancelAnimationFrame(frame); frame = 0; last = 0; }
            wake();
        }) : null;

        class Microdots {
            constructor(root) {
                this.root = root;
                this.canvas = root.querySelector('canvas');
                try { this.ctx = this.canvas?.getContext('2d', { alpha: true }); } catch (_) { return; }
                if (!this.ctx) return;
                this.halo = root.dataset.microdots === 'halo';
                this.auth = root.dataset.microdots === 'auth';
                this.time = 1.4; this.visible = !intersection; this.dots = [];
                effects.set(root, this);
                this.palette(); this.resize();
                if ('ResizeObserver' in window) {
                    this.resizeObserver = new ResizeObserver(() => this.resize());
                    this.resizeObserver.observe(root);
                }
                intersection?.observe(root);
            }
            palette() {
                const style = getComputedStyle(this.root);
                this.colors = ['--microdot-primary','--microdot-highlight','--microdot-gold'].map(key => style.getPropertyValue(key).trim());
                this.intensity = parseFloat(style.getPropertyValue('--microdot-intensity')) || .8;
                if (this.ctx && this.width) this.draw();
            }
            resize() {
                const box = this.root.getBoundingClientRect();
                if (!box.width || !box.height) return;
                this.width = box.width; this.height = box.height;
                const dpr = Math.min(devicePixelRatio || 1, compact.matches ? 1.5 : 2);
                this.canvas.width = Math.round(box.width * dpr); this.canvas.height = Math.round(box.height * dpr);
                this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                const exclude = [];
                let cx = box.width / 2, cy = box.height / 2, inner = 0, outer = 0, bottom = box.height;
                if (this.halo) {
                    const scene = this.root.closest('[data-emblem-scene]');
                    const seal = scene.querySelector('.fc-emblem-float').getBoundingClientRect();
                    cx = seal.left + seal.width / 2 - box.left; cy = seal.top + seal.height / 2 - box.top;
                    inner = Math.max(seal.width, seal.height) / 2 + 15;
                    outer = inner + (compact.matches ? 49 : 78);
                    bottom = scene.querySelector('figcaption').getBoundingClientRect().top - box.top - 8;
                    const copy = document.querySelector('.fc-cinematic-copy');
                    if (copy) exclude.push(copy.getBoundingClientRect());
                    const institution = document.querySelector('.fc-cinematic .institution-line');
                    if (institution) exclude.push(institution.getBoundingClientRect());
                } else if (!this.auth) {
                    const section = this.root.closest('[data-dot-section]') || this.root.parentElement;
                    // Exclude actual text/control rectangles, not their large layout containers.
                    section.querySelectorAll('h1,h2,h3,p,a,span,.fc-cta-caption').forEach(el => exclude.push(el.getBoundingClientRect()));
                }
                const spacing = this.auth ? (compact.matches ? 16 : Math.max(12, Math.sqrt(box.width * box.height / 11000))) : (compact.matches ? 11.5 : 9);
                this.dots = [];
                for (let y = spacing / 2; y < box.height; y += spacing) for (let x = spacing / 2; x < box.width; x += spacing) {
                    if (exclude.some(r => x + box.left > r.left - 8 && x + box.left < r.right + 8 && y + box.top > r.top - 8 && y + box.top < r.bottom + 8)) continue;
                    const dx = x - cx, dy = y - cy, radius = Math.hypot(dx, dy);
                    const mask = this.halo
                        ? smooth(inner, inner + 12, radius) * (1 - smooth(outer - 20, outer, radius)) * (1 - smooth(bottom - 18, bottom, y))
                        : this.auth ? .85 : smooth(0, this.width * .3, x) * (1 - smooth(this.height * .65, this.height, y)) * .9;
                    if (mask < .01) continue;
                    this.dots.push({ x, y, nx:dx / Math.max(box.width,box.height), ny:dy / Math.max(box.width,box.height), angle:Math.atan2(dy,dx), mask, accent:(Math.round(x / spacing) * 17 + Math.round(y / spacing) * 31) % 67 === 0 });
                }
                this.root.dataset.dotCount = this.dots.length;
                this.root.dataset.dotState = reduced.matches ? 'static' : (this.visible ? 'running' : 'paused');
                this.draw(); wake();
            }
            draw() {
                if (!this.ctx) return;
                const ctx = this.ctx, t = this.time;
                ctx.clearRect(0, 0, this.width, this.height);
                // Slowly moving Gaussian lobes + two travelling sine fields. Fixed dot coordinates.
                const ax = Math.cos(t * .19) * .4, ay = Math.sin(t * .23) * .35;
                const bx = Math.sin(t * .14 + 2) * .42, by = Math.cos(t * .17 + 1) * .36;
                // One opening sweep, then only the slower ambient fields. Never replay on resize/theme changes.
                const intro = this.auth && !reduced.matches ? Math.max(0, t - 1.4) : 3;
                this.root.dataset.dotPhase = intro < 2 ? 'intro' : 'ambient';
                for (const dot of this.dots) {
                    const {nx,ny}=dot;
                    const gaussian = Math.exp(-((nx-ax)**2+(ny-ay)**2)/.035);
                    const second = Math.exp(-((nx-bx)**2+(ny-by)**2)/.055);
                    const wave = (Math.sin(nx * 17 + ny * 11 - t * .72 + Math.sin(ny * 8 + t * .21)) + 1) / 2;
                    const orbit = (Math.sin(dot.angle * 3 - t * .47) + 1) / 2;
                    const sweep = intro < 2 ? Math.exp(-((dot.x / this.width + dot.y / this.height * .18 - (intro / 2 * 1.65 - .2)) ** 2) / .009) * (1 - smooth(1.55, 2, intro)) : 0;
                    const light = Math.min(1, .38 * gaussian + .32 * second + .27 * wave * wave + .24 * orbit * wave + .8 * sweep);
                    const alpha = Math.min(.96, (.1 + .92 * light) * dot.mask * this.intensity);
                    const radius = .52 + .76 * light;
                    const color = dot.accent ? this.colors[2] : light > .68 ? this.colors[1] : this.colors[0];
                    if (light > .72) { ctx.fillStyle = `rgba(${color},${alpha * .08})`;ctx.beginPath();ctx.arc(dot.x,dot.y,radius+1.2,0,Math.PI*2);ctx.fill(); }
                    ctx.fillStyle = `rgba(${color},${alpha})`;
                    ctx.beginPath();ctx.arc(dot.x,dot.y,radius,0,Math.PI*2);ctx.fill();
                }
                this.root.classList.add('is-rendered');
            }
            destroy() {
                intersection?.unobserve(this.root); this.resizeObserver?.disconnect();
                this.canvas.width = 0; this.canvas.height = 0; this.ctx = null; this.dots = [];
                effects.delete(this.root); this.root.classList.remove('is-rendered');
            }
        }
        const mount = () => document.querySelectorAll('[data-microdots]').forEach(el => { if (!effects.has(el)) new Microdots(el); });
        mount();
        const mutation = new MutationObserver(records => {
            if (records.some(record => record.type === 'attributes')) effects.forEach(effect => effect.palette());
            if (records.some(record => record.type === 'childList')) {
                effects.forEach(effect => { if (!effect.root.isConnected) effect.destroy(); }); mount();
            }
            wake();
        });
        mutation.observe(document.documentElement, { attributes:true,attributeFilter:['data-theme'],childList:true,subtree:true });
        const reset = () => { effects.forEach(effect => effect.resize()); wake(); };
        window.addEventListener('resize', reset, { passive:true,signal:listeners.signal });
        document.fonts?.ready.then(() => { if (!disposed) reset(); });
        reduced.addEventListener('change', () => { cancelAnimationFrame(frame);frame=0;last=0;effects.forEach(effect => {effect.time=1.4;effect.resize();});wake(); }, {signal:listeners.signal});
        document.addEventListener('visibilitychange', () => {
            cancelAnimationFrame(frame);frame=0;last=0;
            effects.forEach(effect => effect.root.dataset.dotState = document.hidden || !effect.visible ? 'paused' : reduced.matches ? 'static' : 'running');wake();
        }, {signal:listeners.signal});
        window.addEventListener('pagehide', () => {
            disposed=true;cancelAnimationFrame(frame);intersection?.disconnect();mutation.disconnect();listeners.abort();effects.forEach(effect=>effect.destroy());
        }, {once:true,signal:listeners.signal});
        wake();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, {once:true}); else boot();
    window.addEventListener('pageshow', event => { if (event.persisted) boot(); });
})();
