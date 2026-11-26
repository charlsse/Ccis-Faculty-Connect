document.addEventListener('DOMContentLoaded', () => {
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    document.querySelectorAll('[data-fc-carousel]').forEach(root => {
        const slides = [...root.querySelectorAll('[data-fc-slide]')];
        if (!slides.length) return;
        const controls = root.querySelector('[data-fc-controls]');
        const pages = [...root.querySelectorAll('[data-fc-go]')];
        const pause = root.querySelector('[data-fc-pause]');
        const status = root.querySelector('[data-fc-status]');
        const visuals = [...root.querySelectorAll('[data-fc-visual]')];
        const banners = [...root.querySelectorAll('[data-fc-banner]')];
        const interval = Number(root.dataset.interval) || 6500;
        let index = 0, timer = null, paused = false, hovered = false, inView = true;
        let touchStart = null;
        root.classList.add('fc-carousel-ready');
        if (controls) controls.hidden = slides.length < 2;
        const canPlay = () => slides.length > 1 && !paused && !reducedMotion.matches && !hovered
            && !document.hidden && inView && !root.contains(document.activeElement);
        const schedule = () => {
            clearTimeout(timer);
            root.classList.remove('fc-carousel-playing');
            root.style.setProperty('--fc-slide-duration', `${interval}ms`);
            if (pause) {
                pause.hidden = reducedMotion.matches;
                pause.textContent = paused ? '▶' : 'Ⅱ';
                pause.setAttribute('aria-label', paused ? 'Play slideshow' : 'Pause slideshow');
            }
            if (canPlay()) {
                // Restart a compositor-only progress line, without a per-frame JS loop.
                void root.offsetWidth;
                root.classList.add('fc-carousel-playing');
                timer = setTimeout(() => show(index + 1), interval);
            }
        };
        const show = (next, manual = false) => {
            index = (next + slides.length) % slides.length;
            root.dataset.activeSlide = String(index);
            slides.forEach((slide, number) => {
                slide.hidden = number !== index;
                slide.inert = number !== index;
                slide.setAttribute('aria-hidden', String(number !== index));
            });
            visuals.forEach((visual, number) => {
                visual.hidden = number !== index;
                visual.inert = number !== index;
                visual.setAttribute('aria-hidden', String(number !== index));
            });
            banners.forEach((banner, number) => {
                banner.hidden = number !== index;
                banner.setAttribute('aria-hidden', String(number !== index));
            });
            pages.forEach((button, number) => button.setAttribute('aria-pressed', String(number === index)));
            if (manual) {
                paused = true;
                if (status) status.textContent = `Slide ${index + 1} of ${slides.length}`;
            }
            schedule();
        };
        root.querySelector('[data-fc-prev]')?.addEventListener('click', () => show(index - 1, true));
        root.querySelector('[data-fc-next]')?.addEventListener('click', () => show(index + 1, true));
        pages.forEach(button => button.addEventListener('click', () => show(Number(button.dataset.fcGo), true)));
        pause?.addEventListener('click', () => { paused = !paused; schedule(); });
        root.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)
                || !event.target.closest('[data-fc-controls]')) return;
            event.preventDefault();
            show(event.key === 'Home' ? 0 : event.key === 'End' ? slides.length - 1 : index + (event.key === 'ArrowRight' ? 1 : -1), true);
        });
        root.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') { hovered = true; schedule(); } });
        root.addEventListener('pointerleave', () => { hovered = false; schedule(); });
        root.addEventListener('focusin', schedule);
        root.addEventListener('focusout', () => setTimeout(schedule, 0));
        root.addEventListener('touchstart', event => {
            if (event.target.closest('a,button,input') || event.touches.length !== 1) return;
            touchStart = { x: event.touches[0].clientX, y: event.touches[0].clientY };
        }, { passive: true });
        root.addEventListener('touchend', event => {
            if (!touchStart) return;
            const dx = event.changedTouches[0].clientX - touchStart.x;
            const dy = event.changedTouches[0].clientY - touchStart.y;
            if (Math.abs(dx) > 55 && Math.abs(dx) > Math.abs(dy) * 1.5) show(index + (dx < 0 ? 1 : -1), true);
            touchStart = null;
        }, { passive: true });
        root.addEventListener('touchcancel', () => { touchStart = null; });
        document.addEventListener('visibilitychange', schedule);
        reducedMotion.addEventListener('change', schedule);
        if ('IntersectionObserver' in window) new IntersectionObserver(entries => {
            inView = entries[0].isIntersecting; schedule();
        }, { threshold: 0.15 }).observe(root);
        show(0);
    });

    const input = document.querySelector('#site-icon');
    const preview = document.querySelector('[data-icon-preview]');
    if (input && preview) {
        const original = preview.src;
        let url;
        input.addEventListener('change', () => {
            if (url) URL.revokeObjectURL(url);
            preview.src = original;
            const file = input.files[0];
            const feedback = document.querySelector('[data-icon-feedback]');
            document.querySelector('[data-icon-preview-label]').textContent = 'Current icon';
            feedback.textContent = '';
            if (!file) return;
            if (file.type !== 'image/png' || file.size > 512 * 1024) {
                feedback.textContent = 'Choose a PNG up to 512 KB. The server will validate it before saving.';
                return;
            }
            url = URL.createObjectURL(file);
            preview.src = url;
            document.querySelector('[data-icon-preview-label]').textContent = 'Unsaved preview';
            feedback.textContent = 'Preview only. Select Save Website Icon to apply your change.';
        });
    }
});
