document.addEventListener('DOMContentLoaded', () => {
    const bell = document.querySelector('[data-notification-toggle]');
    const panel = document.querySelector('[data-notification-panel]');
    const profile = document.querySelector('[data-profile-toggle]');
    const profilePanel = document.querySelector('[data-profile-menu]');
    const closeNotices = (focus = false) => {
        if (!panel || panel.hidden) return;
        panel.hidden = true;
        bell.setAttribute('aria-expanded', 'false');
        if (focus) bell.focus();
    };
    bell?.addEventListener('click', () => {
        const open = panel.hidden;
        panel.hidden = !open;
        bell.setAttribute('aria-expanded', String(open));
        profilePanel?.classList.remove('open');
        profile?.setAttribute('aria-expanded', 'false');
    });
    profile?.addEventListener('click', () => closeNotices());
    document.addEventListener('click', event => {
        if (!event.target.closest('.notification-menu')) closeNotices();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeNotices(true);
            if (profilePanel?.classList.contains('open')) profile.focus();
        }
    });
    document.addEventListener('focusin', event => {
        if (!event.target.closest('.notification-menu')) closeNotices();
        if (!event.target.closest('.profile-menu')) {
            profilePanel?.classList.remove('open');
            profile?.setAttribute('aria-expanded', 'false');
        }
    });
    const categories = document.querySelector('[data-content-categories]');
    if (!categories) return;
    const tabs = [...categories.querySelectorAll('[data-category]')];
    const panels = [...document.querySelectorAll('[data-editor-panel]')];
    const select = categories.querySelector('[data-category-select]');
    const form = document.querySelector('.content-manager-form');
    const activate = (key, focus = false) => {
        if (!tabs.some(tab => tab.dataset.category === key)) key = 'branding';
        tabs.forEach(tab => {
            const active = tab.dataset.category === key;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            if (active && focus) tab.focus();
        });
        panels.forEach(panel => {
            panel.setAttribute('role', 'tabpanel');
            panel.hidden = panel.dataset.editorPanel !== key;
        });
        select.value = key;
        form.querySelector('.content-editor-actions').hidden = key === 'branding';
        try { sessionStorage.setItem('facultyconnect.content-category', key); } catch (_) {}
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab.dataset.category));
        tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next !== undefined) { event.preventDefault(); activate(tabs[next].dataset.category, true); }
        });
    });
    select.addEventListener('change', () => activate(select.value));
    // Validate the entire existing landing form, revealing the first invalid field
    // before invoking the browser's accessible validation UI.
    form.noValidate = true;
    form.addEventListener('submit', event => {
        const invalid = [...form.elements].find(input => input.willValidate && !input.validity.valid);
        if (invalid) {
            event.preventDefault();
            activate(invalid.closest('[data-editor-panel]').dataset.editorPanel);
            invalid.focus();
            invalid.reportValidity();
        }
    });
    let initial = 'branding';
    try { initial = sessionStorage.getItem('facultyconnect.content-category') || initial; } catch (_) {}
    const errors = JSON.parse(document.querySelector('[data-content-errors]').dataset.contentErrors || '[]');
    const mapping = { site_icon: 'branding', restore_icon: 'branding', carousel_slides: 'hero', carousel_images: 'hero', portal_preview: 'preview', about: 'about', features: 'features', benefits: 'benefits', cta: 'cta' };
    if (errors.length) initial = mapping[errors[0].split('.')[0]] || initial;
    errors.forEach(name => {
        const parts = name.split('.');
        const fieldName = parts.shift() + parts.map(part => `[${part}]`).join('');
        const input = document.getElementsByName(fieldName)[0];
        input?.setAttribute('aria-invalid', 'true');
    });
    categories.classList.add('is-ready');
    activate(initial);
});
