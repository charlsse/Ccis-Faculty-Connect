/* Public-page enhancements. Core links, artwork and content work without JS. */
document.addEventListener('DOMContentLoaded', () => {
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    const mobile = matchMedia('(max-width: 760px)');
    const header = document.querySelector('[data-public-header]');
    const toggle = document.querySelector('[data-glass-menu-toggle]');
    const nav = document.querySelector('#primaryNav');
    const backdrop = document.querySelector('[data-menu-backdrop]');
    const background = [...document.querySelectorAll('#main-content,.fc-footer,.skip-link')];
    let menuOpen = false;
    let bodyOverflow = '';
    let inertState = [];

    const closeMenu = (restoreFocus = true) => {
        if (!menuOpen) { if (nav) nav.inert = mobile.matches; return; }
        menuOpen = false;
        nav.classList.remove('open');
        nav.inert = mobile.matches;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Open navigation');
        backdrop.hidden = true;
        header.removeAttribute('role');
        header.removeAttribute('aria-modal');
        header.removeAttribute('aria-label');
        document.body.style.overflow = bodyOverflow;
        inertState.forEach(([element, wasInert]) => element.inert = wasInert);
        if (restoreFocus) toggle.focus({ preventScroll: true });
    };
    const openMenu = () => {
        if (!mobile.matches || menuOpen) return;
        menuOpen = true;
        bodyOverflow = document.body.style.overflow;
        inertState = background.map(element => [element, element.inert]);
        background.forEach(element => element.inert = true);
        document.body.style.overflow = 'hidden';
        header.setAttribute('role', 'dialog');
        header.setAttribute('aria-modal', 'true');
        header.setAttribute('aria-label', 'Site navigation');
        nav.inert = false;
        backdrop.hidden = false;
        nav.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close navigation');
        nav.querySelector('a')?.focus({ preventScroll: true });
    };
    if (nav && toggle && backdrop) {
        nav.inert = mobile.matches;
        toggle.setAttribute('aria-label', 'Open navigation');
        toggle.addEventListener('click', () => menuOpen ? closeMenu() : openMenu());
        backdrop.addEventListener('click', () => closeMenu());
        header.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
            if (!menuOpen) return;
            closeMenu(false);
            if (link.hash && link.pathname === location.pathname) {
                const destination = document.getElementById(link.hash.slice(1));
                if (destination) {
                    const previous = destination.getAttribute('tabindex');
                    destination.setAttribute('tabindex', '-1');
                    destination.focus({ preventScroll: true });
                    destination.addEventListener('blur', () => previous === null
                        ? destination.removeAttribute('tabindex') : destination.setAttribute('tabindex', previous), { once: true });
                }
            }
        }));
        document.addEventListener('keydown', event => {
            if (!menuOpen) return;
            if (event.key === 'Escape') { event.preventDefault(); closeMenu(); }
            if (event.key !== 'Tab') return;
            const focusable = [...header.querySelectorAll('a[href],button:not([disabled])')].filter(el => el.getClientRects().length && !el.hidden);
            const first = focusable[0], last = focusable.at(-1);
            if (event.shiftKey && (document.activeElement === first || !header.contains(document.activeElement))) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !header.contains(document.activeElement))) {
                event.preventDefault(); first.focus();
            }
        });
        mobile.addEventListener('change', () => {
            const focusInsideMenu = nav.contains(document.activeElement);
            closeMenu(false);
            nav.inert = mobile.matches;
            if (mobile.matches && focusInsideMenu) toggle.focus({ preventScroll: true });
        });
        window.addEventListener('pagehide', () => closeMenu(false));
    }

    const scene = document.querySelector('[data-emblem-scene]');
    const emblem = document.querySelector('[data-emblem]');
    const reset = () => { emblem?.style.removeProperty('--tilt-x'); emblem?.style.removeProperty('--tilt-y'); };
    scene?.addEventListener('pointermove', event => {
        if (motion.matches || event.pointerType === 'touch') return;
        const rect = scene.getBoundingClientRect();
        emblem.style.setProperty('--tilt-x', `${-((event.clientY - rect.top) / rect.height - .5) * 12}deg`);
        emblem.style.setProperty('--tilt-y', `${((event.clientX - rect.left) / rect.width - .5) * 18}deg`);
    });
    scene?.addEventListener('pointerleave', reset);
    motion.addEventListener('change', reset);
    let sceneVisible = true;
    const pauseEmblem = () => scene?.classList.toggle('is-paused', !sceneVisible || document.hidden);
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (entry.target === scene) { sceneVisible = entry.isIntersecting; pauseEmblem(); }
            else if (entry.isIntersecting) {
                if (!motion.matches) entry.target.classList.add('fc-reveal');
                observer.unobserve(entry.target);
            }
        }), { threshold: .12 });
        if (scene) observer.observe(scene);
        document.querySelectorAll('[data-reveal],.fc-editorial,.fc-benefits article,.fc-feature-copy,.fc-community,.fc-cta').forEach(el => observer.observe(el));
        document.querySelectorAll('.fc-benefits article').forEach((el, index) => el.style.setProperty('--reveal-index', index));
        const sentinel = document.querySelector('[data-nav-sentinel]');
        if (sentinel) new IntersectionObserver(([entry]) => header?.classList.toggle('is-scrolled', !entry.isIntersecting)).observe(sentinel);
        const sectionLinks = [...nav.querySelectorAll('a[href^="#"]')];
        const sections = sectionLinks.map(link => document.querySelector(link.hash)).filter(Boolean);
        const visibleSections = new Map();
        const active = new IntersectionObserver(entries => {
            entries.forEach(entry => visibleSections.set(entry.target.id, entry.isIntersecting));
            const current = sections.find(section => visibleSections.get(section.id));
            if (!current) return;
            sectionLinks.forEach(link => link.hash === `#${current.id}` ? link.setAttribute('aria-current', 'location') : link.removeAttribute('aria-current'));
        }, { rootMargin: '-15% 0px -50% 0px', threshold: 0 });
        sections.forEach(section => active.observe(section));
    }
    document.addEventListener('visibilitychange', pauseEmblem);

    // A small directional rim response, only while a fine pointer moves.
    const material = header?.querySelector('.fc-nav-material');
    let highlightFrame = 0;
    header?.addEventListener('pointermove', event => {
        if (motion.matches || event.pointerType !== 'mouse' || highlightFrame) return;
        highlightFrame = requestAnimationFrame(() => {
            const rect = header.getBoundingClientRect();
            material?.style.setProperty('--glass-x', `${event.clientX - rect.left}px`);
            material?.style.setProperty('--glass-y', `${event.clientY - rect.top}px`);
            highlightFrame = 0;
        });
    });
    header?.addEventListener('pointerleave', () => {
        cancelAnimationFrame(highlightFrame); highlightFrame = 0;
        material?.style.removeProperty('--glass-x'); material?.style.removeProperty('--glass-y');
    });

    document.querySelectorAll('[data-role-preview]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll('[data-role-preview]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        document.querySelectorAll('[data-role-panel]').forEach(panel => panel.hidden = panel.dataset.rolePanel !== button.dataset.rolePreview);
    }));

    // Native modal handles inert background, Escape and focus containment.
    // Source-image links are the fallback when dialog/JavaScript is unavailable.
    const dialog = document.querySelector('[data-artwork-dialog]');
    if (dialog && typeof dialog.showModal === 'function') {
        const image = dialog.querySelector('[data-artwork-image]');
        const zoom = dialog.querySelector('[data-artwork-zoom]');
        let opener = null, oldOverflow = '';
        document.querySelectorAll('[data-artwork-open]').forEach(link => link.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            opener = link;
            const carousel = link.closest('[data-fc-carousel]');
            if (carousel?.classList.contains('fc-carousel-playing')) carousel.querySelector('[data-fc-pause]')?.click();
            image.src = link.href;
            image.alt = link.querySelector('img').alt;
            oldOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            dialog.showModal();
            dialog.querySelector('[data-artwork-close]').focus();
        }));
        dialog.querySelector('[data-artwork-close]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
        zoom.addEventListener('click', () => {
            const enlarged = dialog.classList.toggle('is-zoomed');
            zoom.setAttribute('aria-pressed', String(enlarged));
            zoom.textContent = enlarged ? 'Fit image' : 'Enlarge';
        });
        dialog.addEventListener('close', () => {
            document.body.style.overflow = oldOverflow;
            dialog.classList.remove('is-zoomed');
            zoom.setAttribute('aria-pressed', 'false'); zoom.textContent = 'Enlarge';
            opener?.focus({ preventScroll: true });
        });
    }
});

// CSS owns every animation frame; JS only measures after layout/font changes.
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-marquee]');
    if (!root) return;
    const track = root.querySelector('[data-marquee-track]');
    const group = root.querySelector('[data-marquee-group]');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const pixelsPerSecond = 28;
    let measuredWidth = 0, visible = true, fontsReady = false;
    const measure = () => {
        if (!root.classList.contains('is-ready')) return;
        const width = group.getBoundingClientRect().width;
        if (!width || Math.abs(width - measuredWidth) < .01) return;
        // Preserve the current pixel offset when responsive typography changes.
        const animation = track.getAnimations?.()[0];
        const offset = measuredWidth && animation ? ((Number(animation.currentTime) || 0) / 1000 * pixelsPerSecond) % measuredWidth : 0;
        root.style.setProperty('--marquee-duration', `${width / pixelsPerSecond}s`);
        measuredWidth = width;
        const updated = track.getAnimations?.()[0];
        if (updated) updated.currentTime = (offset % width) / pixelsPerSecond * 1000;
    };
    const sync = () => {
        const enabled = fontsReady && !reduced.matches && CSS.supports('animation-name', 'fc-marquee-flow');
        root.classList.toggle('is-ready', enabled);
        if (enabled) measure();
        else measuredWidth = 0;
    };
    const visibility = () => root.classList.toggle('is-offscreen', !visible || document.hidden);
    if ('IntersectionObserver' in window) new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting; visibility();
    }).observe(root);
    document.addEventListener('visibilitychange', visibility);
    if ('ResizeObserver' in window) new ResizeObserver(measure).observe(group);
    else window.addEventListener('resize', measure, { passive: true });
    reduced.addEventListener('change', sync);
    (document.fonts?.ready || Promise.resolve()).then(() => { fontsReady = true; sync(); });
    document.fonts?.addEventListener('loadingdone', measure);
});
