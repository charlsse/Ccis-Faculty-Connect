document.addEventListener('DOMContentLoaded', () => {
    const navToggle = document.querySelector('[data-nav-toggle]');
    const navLinks = document.querySelector('#primaryNav');

    if (navToggle && navLinks && !navToggle.hasAttribute('data-glass-menu-toggle')) {
        navToggle.addEventListener('click', () => {
            const isOpen = navLinks.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', String(isOpen));
        });

        navLinks.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('open');
                navToggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const field = button.closest('.password-field');
            const input = field ? field.querySelector('[data-password-input]') : null;
            if (!input) {
                return;
            }

            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.textContent = shouldShow ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', String(shouldShow));
            button.setAttribute('aria-label', (shouldShow ? 'Hide ' : 'Show ') + (input.name === 'password_confirmation' ? 'confirmation password' : 'password'));
        });
    });

    document.querySelectorAll('[data-password-editor]').forEach(editor => {
        const password = editor.querySelector('[name=password]');
        const confirmation = editor.querySelector('[name=password_confirmation]');
        const meter = editor.querySelector('meter');
        const strengthLabel = editor.querySelector('[data-strength-label]');
        const match = editor.querySelector('[data-password-match]');
        // Match the installed Laravel Password rule's Unicode categories.
        // Values are read only in memory; no persistence, logging or network requests.
        let upper, lower, number, symbol;
        try {
            upper = new RegExp('\\p{Lu}', 'u');
            lower = new RegExp('\\p{Ll}', 'u');
            number = new RegExp('\\p{N}', 'u');
            symbol = new RegExp('[\\p{Z}\\p{S}\\p{P}]', 'u');
        } catch { return; } // Static requirements and server validation remain usable.
        editor.querySelector('[data-password-strength]').hidden = false;
        const update = () => {
            const value = password.value;
            const length = Array.from(value).length;
            const rules = { length: length >= 12, upper: upper.test(value), lower: lower.test(value), number: number.test(value), symbol: symbol.test(value) };
            Object.entries(rules).forEach(([name, passed]) => {
                const item = editor.querySelector(`[data-password-rule="${name}"]`);
                item.classList.toggle('is-met', passed);
                item.querySelector('[data-rule-icon]').textContent = passed ? '\u2713' : '\u25cb';
                item.querySelector('[data-rule-state]').textContent = passed ? 'Met: ' : 'Required: ';
            });
            const count = Object.values(rules).filter(Boolean).length;
            const level = !value ? 0 : count === 5 ? (length >= 16 ? 4 : 3) : (length >= 12 && count >= 4 ? 3 : length >= 8 && count >= 2 ? 2 : 1);
            const description = ['Not entered', 'Weak', 'Fair', 'Good', 'Strong'][level];
            meter.value = level;
            meter.setAttribute('aria-valuetext', description);
            strengthLabel.textContent = description;
            const bothEntered = Boolean(value && confirmation.value);
            const matches = bothEntered && value === confirmation.value;
            match.textContent = !bothEntered ? '' : matches ? '\u2713 Passwords match' : 'Passwords do not match';
            match.classList.toggle('is-met', matches);
        };
        password.addEventListener('input', update);
        confirmation.addEventListener('input', update);
        password.addEventListener('change', update);
        confirmation.addEventListener('change', update);
        window.addEventListener('pageshow', update);
        update();
    });

    document.querySelectorAll('[data-password-submit]').forEach(form => {
        form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            const button = form.querySelector('[type=submit]');
            button.dataset.originalLabel = button.textContent;
            button.disabled = true;
            button.textContent = 'Saving password...';
        });
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-password-submit]').forEach(form => {
            delete form.dataset.submitting;
            const button = form.querySelector('[type=submit]');
            button.disabled = false;
            if (button.dataset.originalLabel) button.textContent = button.dataset.originalLabel;
        });
    });
    document.querySelector('[data-copy-setup-key]')?.addEventListener('click', async event => {
        try {
            await navigator.clipboard.writeText(document.querySelector('#manualSetupKey').textContent);
            event.target.textContent = 'Copied';
        } catch { event.target.textContent = 'Select the key to copy'; }
    });

    document.querySelectorAll('[data-carousel]').forEach((carousel) => {
        const slides = Array.from(carousel.querySelectorAll('.carousel-slide'));
        const dotsHost = carousel.querySelector('[data-carousel-dots]');
        const prev = carousel.querySelector('[data-carousel-prev]');
        const next = carousel.querySelector('[data-carousel-next]');
        let index = 0;
        let timer = null;
        let startX = 0;

        if (!slides.length || !dotsHost) {
            return;
        }

        const dots = slides.map((_, dotIndex) => {
            const dot = document.createElement('button');
            dot.type = 'button';
            dot.setAttribute('aria-label', `Go to slide ${dotIndex + 1}`);
            dot.addEventListener('click', () => showSlide(dotIndex, true));
            dotsHost.appendChild(dot);
            return dot;
        });

        const update = () => {
            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle('active', slideIndex === index);
            });
            dots.forEach((dot, dotIndex) => {
                dot.classList.toggle('active', dotIndex === index);
            });
        };

        const advance = (direction) => {
            index = (index + direction + slides.length) % slides.length;
            update();
        };

        function showSlide(nextIndex, resetTimer) {
            index = nextIndex;
            update();
            if (resetTimer) {
                restart();
            }
        }

        function restart() {
            window.clearInterval(timer);
            timer = window.setInterval(() => advance(1), 5200);
        }

        prev?.addEventListener('click', () => {
            advance(-1);
            restart();
        });

        next?.addEventListener('click', () => {
            advance(1);
            restart();
        });

        carousel.addEventListener('touchstart', (event) => {
            startX = event.touches[0].clientX;
        }, { passive: true });

        carousel.addEventListener('touchend', (event) => {
            const endX = event.changedTouches[0].clientX;
            const delta = endX - startX;
            if (Math.abs(delta) > 45) {
                advance(delta > 0 ? -1 : 1);
                restart();
            }
        }, { passive: true });

        update();
        restart();
    });
});
