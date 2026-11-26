# FacultyConnect liquid-glass refinement

## Delivered

The existing Blade application now has a white/mint/emerald light appearance, vivid yellow primary actions, and a forest/charcoal dark appearance. Light-mode headline emphasis uses dark green over a yellow underline for readable contrast; dark mode uses yellow text. The original CCIS seal PNG, favicon, faculty artwork, and image proportions are unchanged.

### Glass materials

`public/css/premium.css` now contains three reusable materials: `.fc-glass-clear`, `.fc-glass-regular`, and `.fc-glass-elevated`, used with `.fc-liquid-glass`. These combine theme-specific translucency, real backdrop blur and saturation, internal directional light, masked reflective rims, inset highlights, and ambient shadows. A small pointer-driven rim highlight runs only while a mouse moves. The logo, text, controls, and faculty images are not distorted or recolored. There is no WebGL or new dependency.

The compact navbar has a 66px minimum desktop height, allowing its content to grow. An IntersectionObserver switches to a stronger material after 40px of scrolling. The transparent header shell lets the navbar and mobile sheet sample their own backdrops without blurring their text. Unsupported backdrop-filter browsers receive solid material fallbacks; unsupported gradient-mask compositing hides only the decorative rim. Forced-color styling is included.

### Mobile navigation

The existing navigation links now appear in a compact 26px-radius glass sheet beneath the anchored navbar. The page dims and blurs behind it. Opening makes page content inert, locks background scrolling, focuses the first navigation link, and contains keyboard focus within the navigation dialog. Link activation, Escape, backdrop activation, and switching to a desktop viewport close the sheet and release the lock. Escape/backdrop restore the toggle focus; section links transfer focus to their destination. A short viewport gives the menu its own scroll area. No-JavaScript navigation stays visible and usable.

### Hero and faculty section

The hero retains its content, real CTAs, and layered interactive CCIS seal. Its background fades into the same page color as the following section. A one-time image reveal and tighter spacing complete the handoff without scroll interception.

Uploaded artwork dimensions were inspected: 1665x945, 2048x758, and 1672x941. Images render at their original aspect ratios in a maximum 1000px frame, eliminating solid green sidebars and artificial height caps. The original image is linked as a fallback. With JavaScript, it opens in a native modal with Enlarge/Fit controls, keyboard-accessible scrolling, Escape closing, and focus restoration. Carousel rotation pauses when opening the viewer, preserving the originating slide.

Removed the decorative `fc / ✓` element. Added thin theme-aware scrollbars, readable secondary labels, gentle reveal staggering, and complete reduced-motion fallbacks. Shared account/sidebar colors use forest green instead of the previous navy.

## Files changed

- `public/css/premium.css`: replaced the prior finishing layer with the refined theme, materials, layout and responsive rules.
- `public/js/landing.js`: consolidated mobile navigation, scroll state/reveals, emblem motion, rim response and artwork viewer.
- `public/js/facultyconnect.js`: lets the new menu controller own the existing public navigation hooks; other shared behavior stays intact.
- `public/js/appearance.js`: updated browser theme-color values, retaining preference persistence.
- `resources/views/landing.blade.php`: material layer, backdrop, artwork viewer/links, transition heading and decorative cleanup.
- `resources/views/partials/appearance-head.blade.php`: matching light/dark theme-color metadata.
- `tests/Browser/liquid-glass.cjs`: repeatable read-only browser regression.
- `docs/liquid-glass-refinement.md`: this report.
- `docs/premium-ui-refresh.md`: marks the earlier visual report as superseded.

## Verification

- Blade view compilation passed.
- JavaScript syntax checks passed for all three modified scripts.
- Browser regression passed **32 layout cases**: landing and login in both themes at **320, 375, 390, 430, 768, 1024, 1440 and 1920 CSS px**. No horizontal page overflow and no JavaScript page errors.
- **18 grouped interaction checks** passed: mobile bounds/focus loop/Escape/inert state/scroll restoration, backdrop closing, section focus, viewport resize, image viewer/enlargement, carousel, role preview, persisted theme, no-JavaScript fallback, emblem tilt and offscreen pause.
- A 300px-high mobile viewport kept the menu within the viewport with internal scrolling.
- A **200%-equivalent layout** (720 CSS px at device scale factor 2, equivalent to 1440 physical px) passed overflow and menu bounds. This is viewport emulation, not a physical browser zoom test.
- Forced the no-backdrop-filter fallback branch in both themes and verified opaque readable panels and visible controls. This simulates unsupported CSS; it is not a separate legacy-browser run.
- Checked representative contrast pairs: light hero body 4.99:1, light scrolled navigation over dark artwork 7.83:1, dark scrolled navigation over bright artwork 6.31:1, yellow primary CTA 8.34:1; fallback menu text above 12:1. These are conservative color-pair checks, not a full accessibility certification.
- Visually inspected captured desktop light/dark heroes, closed mobile navbar, mobile open menus, actual image detail blurred under the navbar, section transition, full artwork, enlargement dialog, lower security section, fallback menu and the zoom-equivalent layout.
- Full PHPUnit run: **136 tests, 1199 assertions; 134 passed, 2 failed**. These are the same failures reported before this refinement, in untouched views:
  1. `AccountWorkflowTest` line 211 expects `Trust this device for 20 days`; the setup view says `Trust this device`.
  2. `WaterCollectionTest` line 258 expects `Report & CSV export`; the view says `Report & Excel export`.

## Preservation and limits

No route, controller, model, schema, permission, authentication rule, payment processing code, environment setting, or application record was changed. The existing automated suite exercised authentication, roles, member records, content, announcements, security and payment modules with the isolated SQLite in-memory test database. No live payment or authenticated browser transaction was performed. Visual browser verification used headless Chrome on Windows; physical iOS/Safari and Android devices were not tested.

The application still runs normally without a frontend build or additional dependencies. For repeatable public UI checks, serve it on port 8095 and run `node tests/Browser/liquid-glass.cjs`, or set `FC_QA_URL` to another local URL. The script uses the existing local Playwright installation.

## Evidence

Screenshots and machine-readable results are in `tmp/glass-qa/`:

- `hero-light.png`, `hero-dark.png`
- `mobile-light.png`, `mobile-dark.png`
- `menu-light.png`, `menu-dark.png`
- `glass-over-artwork-light.png`, `glass-over-artwork-dark.png`
- `transition-light.png`, `transition-dark.png`
- `artwork-viewer-light.png`, `artwork-viewer-dark.png`
- `fallback-light.png`, `fallback-dark.png`
- `zoom-200-equivalent.png`
- `results.json`, `fallback-contrast.json`

The full PHPUnit output is `tmp/glass-phpunit.txt`; JUnit results are `tmp/glass-phpunit.xml`. The additional fallback/contrast probe is `tmp/glass-fallback.cjs`.
