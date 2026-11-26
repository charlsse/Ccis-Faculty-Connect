# FacultyConnect UI refresh

Superseded visually by [the liquid-glass refinement](liquid-glass-refinement.md). This earlier report records the initial redesign.

## Implementation
- Reused public/images/ccis-logo.png without modifying its pixels or proportions. The existing managed favicon route and its CCIS fallback remain unchanged.
- Added a navy cinematic hero, floating navigation, green environmental glow, gold accents, working explore/sign-in links, and a responsive emblem composition.
- Emblem uses eight CSS depth layers behind the authoritative PNG, perspective, restrained pointer tilt, and gentle floating motion. No new rendering dependency or WebGL requirement. Touch scrolling stays native; reduced motion presents a static seal. Idle motion pauses off-screen or while the document is hidden.
- Kept the administrator-managed carousel, content, feature availability labels, and existing role previews. Replaced a fabricated example payment amount with a contribution process explanation.
- Added entrance and observer-triggered reveals, card hover treatment, outside-click/focus and Escape closing for public navigation.
- Applied shared refinements to existing authenticated layouts: navy sidebar, rounded controls and cards, and table spacing. All underlying forms, routes, permissions, controllers, models, payment integrations, and database records remain unchanged.

## Files
- resources/views/landing.blade.php
- resources/views/layouts/app.blade.php
- resources/views/layouts/auth.blade.php
- resources/views/layouts/dashboard.blade.php
- public/css/premium.css (new)
- public/js/landing.js
- docs/premium-ui-refresh.md (this report)
- tmp/premium-check.cjs and tmp/premium-interactions.cjs (local browser verification scripts)

## Validation
- php artisan view:cache: passed.
- node --check public/js/landing.js: passed.
- PHPUnit: 136 tests, 1199 assertions, 134 passing and 2 failing.
  - AccountWorkflowTest line 211 expects "Trust this device for 20 days"; the untouched setup view says "Trust this device".
  - WaterCollectionTest line 258 expects "Report & CSV export"; the untouched report view says "Report & Excel export".
  These expectations were not changed as part of the presentation work.
- Headless Chrome: landing and login at 320, 375, 390, 768, 1024 and 1440px in both themes (24 page/viewport/theme combinations): no horizontal document overflow and no JavaScript errors.
- Verified mobile menu opens and closes with Escape, carousel next navigation, role preview switching, pointer tilt, reduced-motion static transform, no-JavaScript mobile overflow fallback, and HTTP 200 for the actual favicon URL.
- Visually inspected desktop and mobile landing screenshots. Screenshots are in tmp/premium-desktop.png and tmp/premium-mobile.png; full-page captures are also present.

## Limits and running
- Authenticated screens received shared styling, but were not manually exercised in a signed-in browser session; automated application tests provide the functional coverage reported above. Live payment transactions were not performed.
- Existing uploaded carousel imagery is retained. No new faculty imagery was fabricated. Separate MotionSites/GetLayers reference screenshots were not available beyond the written brief.
- No npm build or new dependency is needed. Serve the existing Laravel application normally, or use php artisan serve. Refresh the browser to load the new public stylesheet.
