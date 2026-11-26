(() => {
    const root = document.documentElement;
    const preference = matchMedia('(prefers-color-scheme: dark)');
    let saved;
    try { saved = localStorage.getItem('facultyconnect.theme'); } catch (_) {}
    const valid = value => value === 'light' || value === 'dark';
    let override = valid(saved) ? saved : null;
    const apply = theme => {
        root.dataset.theme = theme;
        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            button.hidden = false;
            button.setAttribute('aria-label', `Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`);
            button.title = button.getAttribute('aria-label');
        });
        document.querySelectorAll('meta[name="theme-color"]').forEach(meta => {
            meta.content = theme === 'dark' ? '#0b1711' : '#fbfefc';
        });
    };
    apply(override || (preference.matches ? 'dark' : 'light'));
    document.querySelectorAll('[data-theme-toggle]').forEach(button => button.addEventListener('click', () => {
        override = root.dataset.theme === 'dark' ? 'light' : 'dark';
        try { localStorage.setItem('facultyconnect.theme', override); } catch (_) {}
        apply(override);
    }));
    preference.addEventListener('change', () => { if (!override) apply(preference.matches ? 'dark' : 'light'); });
    window.addEventListener('storage', event => {
        if (event.key !== 'facultyconnect.theme' && event.key !== null) return;
        override = valid(event.newValue) ? event.newValue : null;
        apply(override || (preference.matches ? 'dark' : 'light'));
    });
})();
