# Flowing microdots

The public landing page now uses a reusable Canvas 2D renderer for the seal halo and two quieter section accents. Fixed grid positions combine moving Gaussian fields, travelling sine waves, and angular modulation to change brightness and radius continuously. The official seal remains unchanged, with a protected central area and independent pointer interaction.

## Files changed

- `resources/views/landing.blade.php`: mounts three decorative canvases and loads the renderer.
- `public/js/microdots.js`: new renderer, theme adaptation, responsive sizing, visibility scheduling, reduced motion, and cleanup.
- `public/css/premium.css`: matrix layering, theme palettes, final intensity, and static fallback; replaces the old CSS dot effects.
- `public/js/landing.js`: removes the obsolete CSS accent visibility handler.
- `tests/Browser/microdots.cjs`: new repeatable browser verification.
- `docs/flowing-microdots.md`: this implementation and verification record.

## Rendering budget

One shared animation scheduler caps desktop updates at 30 fps and mobile at 24 fps. Grid spacing increases from 9 to 11.5 CSS pixels on phones; device pixel ratio is capped at 2 and 1.5 respectively. Rendering stops offscreen or when the document is hidden. Reduced motion produces a static frame. Removed components release their canvas and observers; page exit tears down shared listeners.

## Verification

The effect was temporarily previewed at intensity 2.4 in the browser, then restored to production intensity (.88 light halo, .86 dark halo, .52 secondary accents, .5 CTA). No debug intensity ships in the source.

Chrome screenshots were inspected in both themes on desktop and mobile. Separate frames show shifting illuminated clusters. Raw canvas comparisons over 1.2 seconds measured 4,622 changed pixels in light mode and 4,348 in dark mode, with 34 draw updates in each sample. The protected seal center contained no dot pixels.

- `node tests/Browser/microdots.cjs`: 16 responsive/theme cases; motion, visible pixels, protected center, offscreen suspension, reduced motion, simulated document visibility, removal cleanup, and canvas fallback.
- `node tests/Browser/liquid-glass.cjs`: 32 layout cases and 18 interaction checks; no page errors.
- `php packages/phpunit/phpunit/phpunit --filter 'AppearanceTest|AdminContentManagerTest'`: 9 tests, 73 assertions.
- JavaScript syntax and Laravel view compilation passed.

Evidence is in `tmp/microdots-qa/`: high-intensity debug images, final light/dark frames A and B, mobile screenshots, CTA screenshots, and machine-readable results. Tests use desktop Chrome with responsive viewports; physical-device performance was not measured. The supplied screenshot and written reference guided the effect; the Pinterest page could not be opened.
