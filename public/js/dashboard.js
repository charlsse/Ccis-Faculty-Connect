document.addEventListener('DOMContentLoaded', () => {
    const locks = new Set();
    let savedBody;
    window.FacultyOverlayLock = {
        acquire(owner) {
            if (locks.has(owner)) return;
            if (!locks.size) {
                savedBody = { css: document.body.style.cssText, x: scrollX, y: scrollY };
                Object.assign(document.body.style, { position: 'fixed', top: -savedBody.y + 'px', left: '0', width: '100%', overflow: 'hidden' });
            }
            locks.add(owner);
        },
        release(owner) {
            if (!locks.delete(owner) || locks.size) return;
            document.body.style.cssText = savedBody.css;
            window.scrollTo({ left: savedBody.x, top: savedBody.y, behavior: 'instant' });
        }
    };
    const sidebar = document.querySelector('#dashboardSidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const openButton = document.querySelector('[data-sidebar-open]');
    const closeButtons = document.querySelectorAll('[data-sidebar-close]');
    const profileToggle = document.querySelector('[data-profile-toggle]');
    const profileMenu = document.querySelector('[data-profile-menu]');

    const positionProfileMenu = () => {
        if (!profileMenu?.classList.contains('open') || !profileToggle) return;
        const viewport = window.visualViewport;
        const left = (viewport?.offsetLeft || 0) + 12;
        const top = (viewport?.offsetTop || 0) + 12;
        const right = left + (viewport?.width || innerWidth) - 24;
        const bottom = top + (viewport?.height || innerHeight) - 24;
        const trigger = profileToggle.getBoundingClientRect();
        const width = Math.min(280, right - left);
        Object.assign(profileMenu.style, { width: `${width}px`, maxHeight: 'none', right: 'auto', bottom: 'auto' });
        const naturalHeight = profileMenu.scrollHeight + 2;
        const below = Math.max(0, bottom - trigger.bottom - 10);
        const above = Math.max(0, trigger.top - top - 10);
        const upwards = naturalHeight > below && above > below;
        const height = Math.min(naturalHeight, upwards ? above : below);
        Object.assign(profileMenu.style, {
            left: `${Math.max(left, Math.min(trigger.right - width, right - width))}px`,
            top: `${Math.max(top, upwards ? trigger.top - 10 - height : trigger.bottom + 10)}px`,
            maxHeight: `${height}px`,
            transformOrigin: upwards ? 'bottom right' : 'top right',
        });
        profileMenu.dataset.placement = upwards ? 'above' : 'below';
    };
    let profilePositionFrame = 0;
    const scheduleProfilePosition = () => {
        if (profilePositionFrame || !profileMenu?.classList.contains('open')) return;
        profilePositionFrame = requestAnimationFrame(() => { profilePositionFrame = 0; positionProfileMenu(); });
    };
    window.addEventListener('resize', scheduleProfilePosition, { passive: true });
    window.addEventListener('scroll', scheduleProfilePosition, { passive: true });
    window.visualViewport?.addEventListener('resize', scheduleProfilePosition, { passive: true });
    window.visualViewport?.addEventListener('scroll', scheduleProfilePosition, { passive: true });

    const openSidebar = () => {
        sidebar?.removeAttribute('inert');
        sidebar?.classList.add('open');
        sidebar?.setAttribute('role', 'dialog');
        sidebar?.setAttribute('aria-modal', 'true');
        openButton?.setAttribute('aria-expanded', 'true');
        document.querySelector('.dashboard-main')?.setAttribute('inert','');
        window.FacultyOverlayLock.acquire('sidebar');
        document.querySelector('.sidebar-close')?.focus({ preventScroll: true });
        overlay?.classList.add('open');
    };

    const closeSidebar = () => {
        const wasOpen = sidebar?.classList.contains('open');
        sidebar?.classList.remove('open');
        sidebar?.removeAttribute('role');
        sidebar?.removeAttribute('aria-modal');
        if (window.matchMedia('(max-width:980px)').matches) sidebar?.setAttribute('inert', '');
        openButton?.setAttribute('aria-expanded', 'false');
        document.querySelector('.dashboard-main')?.removeAttribute('inert');
        window.FacultyOverlayLock.release('sidebar');
        if(wasOpen) openButton?.focus({ preventScroll: true });
        overlay?.classList.remove('open');
    };

    const desktopNavigation = window.matchMedia('(min-width:981px)');
    if (!desktopNavigation.matches) sidebar?.setAttribute('inert', '');
    desktopNavigation.addEventListener('change', event => {
        closeSidebar();
        if (event.matches) sidebar?.removeAttribute('inert');
    });
    document.querySelector('.sidebar-nav a.active')?.setAttribute('aria-current','page');
    openButton?.setAttribute('aria-controls', 'dashboardSidebar');
    openButton?.setAttribute('aria-expanded', 'false');
    document.addEventListener('keydown', event => {
        if(event.key === 'Escape') { closeSidebar(); profileMenu?.classList.remove('open'); profileToggle?.setAttribute('aria-expanded','false'); }
        if(event.key === 'Tab' && sidebar?.classList.contains('open')) {
            const items = [...sidebar.querySelectorAll('a,button,[tabindex="0"]')].filter(el => el.getClientRects().length);
            const first=items[0], last=items[items.length-1];
            if(event.shiftKey && document.activeElement === first){event.preventDefault();last.focus();}
            else if(!event.shiftKey && document.activeElement === last){event.preventDefault();first.focus();}
        }
    });
    sidebar?.querySelectorAll('.sidebar-nav a').forEach(link => link.addEventListener('click', closeSidebar));
    openButton?.addEventListener('click', openSidebar);
    closeButtons.forEach((button) => button.addEventListener('click', closeSidebar));

    profileToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = profileMenu?.classList.toggle('open');
        profileToggle.setAttribute('aria-expanded', String(Boolean(isOpen)));
        if (isOpen) { profileMenu.scrollTop = 0; positionProfileMenu(); }
    });

    document.addEventListener('click', (event) => {
        if (!profileMenu || !profileToggle) {
            return;
        }

        if (!profileMenu.contains(event.target) && !profileToggle.contains(event.target)) {
            profileMenu.classList.remove('open');
            profileToggle.setAttribute('aria-expanded', 'false');
        }
    });
});
