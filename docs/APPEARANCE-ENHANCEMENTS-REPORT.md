# FacultyConnect appearance enhancements

Implemented September 28, 2026 in the existing Laravel application. The supplied two-page PDF was inspected as visual reference. The current FacultyConnect identity, CMS content, permissions, authentication and payment workflows were retained.

## What changed

- The CMS-driven hero automatically rotates every 6.5 seconds, with three default capability stories and matching workspace, contribution and security previews. Each CMS slide can select its interface preview. Existing saved text/images remain editable and are not overwritten.
- Public, authentication and dashboard layouts share a light/dark toggle. Dark mode uses near-black forest surfaces, mint text, restrained gold and CSS dotted gradients on selected ambient areas. Forms and tables use solid readable surfaces.
- The dashboard now shows a faculty bulletin directly after personal water contributions. It replaces the former recent-announcement list rather than duplicating it. It supports empty, single-announcement and multiple-announcement states, controlled excerpts and links to the existing detail pages.
- Browser and Apple-touch icon links use the configured site icon. The default PNG and fallback ICO were derived from the existing CCIS logo.
- Content Manager includes a separate Site Branding form with current icon, unsaved preview, upload validation, save and restore-default controls. The existing landing-content form still works independently.
- Mobile controls remain at least 44px. The mobile hero security note now occupies its own space below the interface preview rather than covering its text. Personal contribution spacing is more compact on phones.

## Theme persistence

The `facultyconnect.theme` localStorage value takes priority. With no saved override, the operating-system color scheme is used and changes are followed. Choosing a theme saves a browser preference for both guests and signed-in users; it is not an account-synchronized preference. Storage failures are caught, so the switch still works for the current page. A storage listener synchronizes changes across tabs.

A small head script sets the HTML theme attribute before styles and body rendering. The normal theme controller updates toggle labels and browser theme-color metadata. Surface transitions are 240ms and respect reduced motion. No user preference table or account field was added.

## Slideshow behavior and data

Both slideshows use one vanilla JavaScript controller. They provide numbered selection, previous/next, explicit pause/play, keyboard arrows/Home/End, and horizontal swipe. Manual navigation pauses rotation until Play is chosen. Rotation also pauses while hovered, focused, outside the viewport, or in a hidden tab. Reduced motion disables automatic rotation and decorative animation. Progress indicators use CSS; there is no per-frame JavaScript loop.

Slides share a grid track sized to its tallest item. Inactive slides retain layout space but are hidden, inert and removed from the accessibility tree. Only manual changes produce a polite slide-status announcement. The public page retains its no-JavaScript content fallback. A single item needs no controls or timer.

The hero uses the existing `SiteContent::landing()` data. Default copy focuses on the faculty workspace, external contributions and secure access. Saved CMS copy takes precedence. A new optional `visual` value is stored alongside each existing slide.

The bulletin reuses the dashboard controller's existing `Announcement::published()->orderedForPortal()->limit(3)` collection. Publication/expiry filters and pinned-first ordering remain unchanged. No announcement controller/model authorization was changed. The existing `audience` field is descriptive text, not an access-control rule; the preview introduces no broader query or public announcement endpoint.

## Icon management and security

Administrators save branding through the existing admin-only route group and Content Manager controller. Metadata is stored as the `site.branding` row in the existing `site_contents` table. Landing-content saves do not reset this separate key.

Uploads accept square PNG only, 180–512px and at most 512KB. Laravel validates the file, image, MIME, extension, dimensions and size. SVG and executable extensions are rejected. The server generates UUID filenames with a fixed `.png` suffix and stores them using Laravel's public disk. Original filenames are not used. CSRF, authenticated account/onboarding/2FA middleware, the admin role check and a form rate limit apply.

The public icon endpoint serves only the current server-managed path matching the expected UUID/PNG pattern. It does not accept file paths from requests. Responses use `image/png`, inline disposition and `X-Content-Type-Options: nosniff`. Serving through the endpoint avoids requiring a new storage symlink.

Each replacement or restore creates a stable new version in the icon URL. Matching version URLs are cacheable; unmatched or unversioned requests require revalidation. The default favicon remains available if the configured file is missing. Previous uploads are retained rather than deleted, so icon replacement does not destructively remove files; routine cleanup of superseded icons can be added later if needed.

## Files added or modified

| File | Purpose |
| --- | --- |
| `resources/views/layouts/app.blade.php` | Shared appearance head, stylesheet and scripts |
| `resources/views/layouts/auth.blade.php` | Shared theme/icon support and accessible theme control |
| `resources/views/layouts/dashboard.blade.php` | Shared theme/icon support and topbar toggle |
| `resources/views/partials/appearance-head.blade.php` | Early theme initialization and icon metadata |
| `resources/views/partials/theme-toggle.blade.php` | Sun/moon control with inline SVG |
| `resources/views/partials/hero-preview.blade.php` | Three illustrative capability previews |
| `resources/views/landing.blade.php` | Hero slideshow wiring, paired visuals and navigation control |
| `resources/views/dashboard/index.blade.php` | Bulletin placement and removal of duplicated announcement list |
| `resources/views/dashboard/partials/announcement-preview.blade.php` | Real announcement carousel and empty state |
| `resources/views/dashboard/content-manager.blade.php` | Branding form and slide visual selector |
| `public/css/appearance.css` | Theme tokens, dark component styling, dotted motifs and responsive carousels |
| `public/js/appearance.js` | Preference persistence, system changes and toggle behavior |
| `public/js/carousels.js` | Shared slideshow controller and local icon preview |
| `public/js/landing.js` | Removed the superseded manual-only slide handler; retained role/menu behavior |
| `public/images/site-icon.png` | 180px default icon derived from the CCIS logo |
| `public/favicon.ico` | 32px fallback icon replacing the empty file |
| `app/Models/SiteContent.php` | Branding lookup and updated default slide stories |
| `app/Http/Controllers/AdminContentController.php` | Validated branding save/restore and slide visual persistence |
| `app/Http/Controllers/SiteIconController.php` | Safe public icon response and cache headers |
| `routes/web.php` | Added icon GET and protected branding POST routes |
| `tests/Feature/AppearanceTest.php` | Branding authorization/validation, render and announcement regression checks |
| `tests/Browser/appearance.cjs` | Theme, carousel, upload, responsive and content-state checks |
| `tests/Browser/responsive-audit.cjs` | Selectable color scheme and explicit navigation HTTP-error detection |
| `tools/appearance-browser-fixture.php` | Guarded, isolated synthetic announcement fixtures |

## Migrations, dependencies and performance

No migrations and no packages added or removed. Existing PHP, Blade, vanilla JavaScript, CSS and QA tools were reused. No .env values, database connections, production credentials or existing user accounts were changed. No deployment was performed.

New client assets are approximately 20KB of CSS and 8KB of JavaScript uncompressed. The default PNG is approximately 38KB and the fallback ICO approximately 2KB. Dot patterns use CSS instead of image downloads. No carousel framework, React/Vue, autoplay video, analytics or new external data service was introduced. The existing Manrope font request remains unchanged.

## Verification actually performed

| Check | Final result |
| --- | --- |
| Complete PHP regression suite | **96 tests, 769 assertions passed** |
| Existing workflow/layout audit, light mode | **434 checks passed**, no recorded JavaScript/navigation HTTP errors |
| Existing workflow/layout audit, dark mode | **434 checks passed**, no recorded JavaScript/navigation HTTP errors |
| New appearance browser suite | **312 layout checks passed**, all listed interactions passed, no recorded errors |
| Final mobile preview-note refinement | **78 hero checks passed**: three slides, both themes, 13 widths; no overflow/overlap and stable heights |
| Existing public redesign interaction test | Passed 13 widths, role previews, manual messages, menu/Escape and reduced-motion checks |
| Blade/PHP/JavaScript validation | Views cached; corrected compiled Content Manager PHP and changed controller/scripts passed syntax checks |

During QA, screenshot inspection exposed a Content Manager Blade parsing error that the previous layout audit did not detect because it lacked HTTP-status assertions. The template was corrected, a successful-render assertion was added to the PHP test, and both full theme audits were rerun with navigation HTTP-error detection. The final results above refer to those corrected runs.

The broad audits cover 31 page states: onboarding/authentication, role dashboards, profiles, security, account management, directory, announcements, Content Manager, water records, upload review dialog, Treasurer queue, collection reports and receipt. Phone password reset, temporary-password replacement, password feedback/toggles, menu behavior and payment-proof review remained functional. Payment confirmation was tested without submitting an external payment.

The appearance suite exercises hero auto-advance, hover/focus pause, keyboard End, looping, persistent manual pause, theme persistence across navigation/reload, OS preference changes, manual override, blocked localStorage, reduced motion, no-JavaScript fallback, dashboard 0/1/3-announcement states, long titles/excerpts, bulletin rotation/swipe, and the full icon upload/error/restore flow. Backend tests cover unauthorized roles, invalid files, version changes, restore, preservation across content saves, and exclusion of draft/future/expired announcements.

### Viewports

Appearance checks: **320, 360, 375, 390, 393, 412, 430, 480, 768, 1024, 1280, 1440, 1920px**, using a 900px test height. Screenshots use 1440×1000 desktop and 390×844 mobile viewports.

Broader workflow audits in each theme: 320×800, 360×800, 375×812, 390×844, 412×915, 430×932, 768×1024, 1024×768, 1280×720, 1366×768, 1440×900, 1536×864, 1600×900 and 1920×1080.

### Screenshots and isolation

Captured all three hero slides in light/dark at desktop/mobile widths, dashboard light/dark desktop/mobile, Content Manager light/dark desktop/mobile, and dark login screenshots. Visually inspected representative landing/dashboard views in all four combinations, the security preview, Content Manager icon controls, and additional dark security, Treasurer dashboard and water-report views. Fixed the mobile floating-note overlap and verified it across all requested widths after inspection.

Artifacts and JSON results are under `storage/app/private/appearance-qa`, `storage/app/private/responsive-qa` and `storage/app/private/responsive-qa-dark`. PHP tests use SQLite in memory. Browser tests use guarded synthetic accounts and announcements in the existing isolated QA SQLite database on loopback port 8092. No real faculty announcements or account records were replaced by fixtures.

## Limits and recommendations

Chrome headless viewport emulation was used. Safari, Firefox, physical phones, browser chrome's favicon refresh timing, screen readers and a formal WCAG contrast audit were not verified. PNG responses, rendered icon previews and versioned favicon links were verified. Background-tab timing and cross-tab synchronization are implemented but were not separately asserted in the browser suite. PDF receipt generation was not changed or visually reverified.

Saved CMS copy is intentionally preserved and may differ from the new default stories. Administrators can pair each saved slide with its desired preview through Content Manager. Theme preference is browser-local, not synchronized between devices. No known blocking issues remain in the tested flows; real-device and screen-reader acceptance testing are the recommended next checks.
