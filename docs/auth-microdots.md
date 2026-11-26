# Authentication microdot enhancement

The existing two-panel authentication layout now sits above a full-viewport Canvas 2D matrix. A single diagonal illumination sweep runs during approximately the first two seconds, then settles into the existing slow Gaussian and sine-wave fields. The dots retain fixed grid positions. Glass panels use translucent surfaces, edge highlights, shadows, and background blur, with short fade-up entrances. Inputs and text receive no blur filters or individual animation.

The application has no public registration route. The shared authentication layout covers login and the current account-onboarding password form, along with password recovery and other authentication views. No routes, validation, sessions, authentication logic, or account data were changed.

## Changed files

- `resources/views/layouts/auth.blade.php`: mounts the decorative background and shared renderer.
- `public/js/microdots.js`: adds a full-viewport mode and one-time opening wave; retains existing landing effects.
- `public/css/premium.css`: overrides the static authentication dots; adds glass surfaces, theme colors, and reduced-motion-aware entrances.
- `tests/Browser/auth-microdots.cjs`: responsive and motion verification.
- `tests/Browser/render-auth-preview.php`: renders the real onboarding Blade template without creating accounts or changing the database.
- `docs/auth-microdots.md`: this record.

## Verification

- New browser check: 16 responsive/theme cases across login and the rendered onboarding template, at 320, 390, 768, and 1440 pixels. Verified intro-to-ambient transition, changing canvas frames, nonzero dimensions, no horizontal overflow, password visibility, native login validity, reduced motion, and no JavaScript errors.
- Onboarding visual verification uses the actual rendered template supplied through a browser test interception; it does not bypass the live application's authentication guards or exercise a live account session.
- Screenshots in `tmp/auth-microdots-qa/` include opening and ambient frames, light/dark desktop, and mobile. Desktop login and mobile onboarding were visually inspected.
- Targeted PHP run: 37 of 38 tests passed, 286 assertions. The existing AccountWorkflowTest failure expects `Trust this device for 20 days`; the current unchanged template says `Trust this device`.
- JavaScript syntax and Laravel view compilation passed.

Desktop dot count is bounded near 11,000, with wider spacing on phones. Existing frame caps, DPR caps, hidden-tab/offscreen suspension, component cleanup, reduced motion, and static canvas fallback remain shared with the landing renderer. Physical-device performance was not measured. The optional pointer trail was omitted to keep form interaction visually quiet.
