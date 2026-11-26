# Theme banners and ambient micro-dots

## Changes

- Copied the exact supplied banner artwork from Downloads into local project assets. SHA-256 checks confirm the files are byte-for-byte copies:
  - Dark: `public/images/backgrounds/ccis-dark.jpg` from `515186808_1198866458921290_7541344641804967199_n.jpg` (169,007 bytes).
  - Light: `public/images/backgrounds/ccis-light.jpg` from `459889270_971530878321517_1512911684768625981_n.jpg` (465,270 bytes).
- Added two decorative hero scene layers. Existing `html[data-theme]` controls their opacity. Each layer includes its own original image, directional readability overlay, upper-area softening, and bottom gradient into the page.
- A 600ms opacity dissolve changes the complete scene; foreground colors transition with it. Reduced motion switches immediately. Both assets remain local; no external service or new dependency is required.
- Added a masked annular micro-dot field around the existing 3D seal. Three small CSS radial-dot layers drift a few pixels/2 degrees over 18–27 seconds with gentle opacity variation. The transparent center, bottom clipping, and background stacking protect the original seal and caption. Logo image, proportions, pointer tilt, and idle motion are unchanged.
- Added localized halftone shimmer at the faculty community panel's upper corner and the closing CTA's right edge. No full-page wallpaper or effects on records/forms.
- Mobile uses a rightward architectural crop, stronger protection behind the copy, and fewer dot layers. Decorative layers are `aria-hidden`, cannot receive pointer events, and do not alter document geometry.
- Halo motion shares the emblem's existing offscreen pause. Other accents use IntersectionObserver and document visibility to suspend animations. Motion uses CSS transform/opacity, with no canvas, shaders or JS frame loop. Reduced motion leaves a static texture; unsupported masking and forced-color/print modes hide decoration.
- The requested removal of the marquee pause control remains intact.

## Updated files

- `resources/views/landing.blade.php`: hero scenes, seal halo and two halftone containers.
- `public/css/premium.css`: scene compositing, crossfade, dots, responsive and accessibility fallbacks.
- `public/js/landing.js`: offscreen/hidden-tab suspension for ambient accents.
- `public/images/backgrounds/ccis-dark.jpg` and `ccis-light.jpg`: original local assets.
- `docs/ambient-backgrounds.md`: this report.

## Validation

- Visually inspected desktop light/dark, mobile light/dark, tablet and closing CTA screenshots; refined the overlays after the first pass to soften background slogans.
- 16 dedicated theme/viewport cases at 320, 375, 390, 430, 768, 1024, 1440 and 1920px passed without horizontal overflow or JavaScript errors.
- Verified a live theme transition has intermediate opacity between 0 and 1, followed by the correct endpoint.
- Verified HTTP 200 for both banner assets and original-file hash equality.
- Verified decorative pointer transparency, offscreen halo suspension, active CTA shimmer, reduced-motion static behavior, zero transition duration under reduced motion, and absence of the removed marquee pause control.
- Existing browser regression: 32 layout cases and 18 grouped interactions passed, covering the public menu, themes, image viewer, carousel, no-JavaScript fallback and emblem behavior.
- Targeted PHP AppearanceTest and AdminContentManagerTest: 9 tests, 73 assertions, all passed.
- Blade compilation and JavaScript syntax checks passed.

No backend, authentication, routes, permissions, database, faculty artwork or payment logic was edited. Testing used headless Chrome on Windows; live transactions and physical mobile devices were not tested.

Screenshots/results: `tmp/ambient-qa/`. Repeatable local probes: `tmp/ambient-preview.cjs` and `tmp/ambient-check.cjs`, using the existing local Playwright installation against port 8095.
