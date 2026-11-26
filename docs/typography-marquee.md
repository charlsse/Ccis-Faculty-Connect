# Infinite typography marquee

Implemented in `resources/views/landing.blade.php`, `public/css/premium.css`, and `public/js/landing.js`.

- Placed between the existing cinematic hero and faculty artwork.
- Uses the existing Manrope family, medium-weight uppercase text, alternating emerald/gold colors, and real 1px vertical dividers.
- Two identical visual groups, each containing two sequences, exceed the masked viewport at every tested size. A linear CSS transform moves exactly one group width with no track gap.
- Constant 28 CSS px/second: approximately 27.9–34.1 seconds per four-phrase sequence. JavaScript measures only on font/layout changes, preserving the current pixel offset during resizing; it does not run an animation frame loop.
- CSS mask-image and -webkit-mask-image fade 10% at each desktop edge, 7% on mobile.
- No manual pause control; offscreen/hidden-page suspension, one accessible text sequence, and readable reduced-motion/no-JavaScript/unsupported-animation fallbacks. Fixed minimum block sizes avoid shifting adjacent content when the static presentation becomes animated.
- No new dependencies. Logo, faculty artwork, routes, authentication, payments and backend untouched.

Validation:

- Visually inspected desktop, tablet and mobile screenshots in both themes.
- 16 marquee viewport/theme cases at 320, 375, 390, 430, 768, 1024, 1440 and 1920px: no overflow; identical group sizes within subpixel rounding; each group wider than the viewport; constant speed.
- Screenshots at exactly zero and one full animation duration were pixel-identical in both themes.
- Resize preserved a -420px offset from desktop to mobile. Animated and static mobile layouts both occupied 100px.
- Reduced motion and no-JavaScript fallbacks passed; accessibility snapshot exposes the four-phrase sequence once.
- Full-opacity text contrast: light emerald 7.26:1, light gold 5.46:1, dark emerald 9.92:1, dark gold 9.90:1. Edge fading intentionally lowers opacity outside the central reading area; the accessible/static sequence remains available.
- Existing liquid-glass browser regression: 32 layout cases and 18 grouped interaction checks passed, no JavaScript errors.
- AppearanceTest and AdminContentManagerTest: 9 tests / 73 assertions passed.
- Blade compilation and JavaScript syntax checks passed.

Evidence: `tmp/marquee-qa/`. Browser probes: `tmp/marquee-check.cjs` and `tmp/marquee-resize.cjs` (use existing local Playwright with the app served on port 8095). Visual testing used headless Chrome; physical-device testing was not performed.
