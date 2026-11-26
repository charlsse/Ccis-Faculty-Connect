# FacultyConnect visual redesign — September 27, 2026

Implemented in the existing Laravel/Blade application. No deployment, migrations, credentials, production accounts, routes, policies, or payment services changed.

## Design and audit

The public page now uses an editorial composition, warm neutral surfaces, forest/emerald gradients and restrained gold accents. Distinct contribution, security, role, community, module and footer treatments replace the repeated card grids. Interface previews are explicitly illustrative and contain no private faculty records. Payments are explicitly described as external.

The three shared layouts load a common token-based design system. The portal uses a 244px desktop sidebar, a compact header, neutral page headings, a prominent personal contribution area, restrained summary metrics, consistent forms and table styling. Existing phone table transformations, file upload/review dialogs, permissions and form endpoints remain in place. Authentication uses the same visual language with a forest identity panel and a simple phone form.

Audit covered routes (including water routes), controller and middleware responsibilities, role permissions, models/CMS behavior, migration structure, shared layouts, dashboard and authentication views, CSS/JavaScript and existing test fixtures. There is no root package.json or active Vite configuration: the layouts load public assets directly. Unused resource scaffold files were left alone. Existing desktop density overrides and module styles remain, with the shared design stylesheet deliberately loaded last. No hosting configuration was present.

CMS fields for slides/images, about content, benefits, features/statuses, preview metrics/bars and CTA remain rendered. Updated fallback copy removes outdated Phase 1 language and accurately labels existing modules; saved CMS values are not overwritten. This means saved outdated copy/statuses still need editorial review in Content Manager.

## Important files

- resources/views/landing.blade.php — complete public composition, preserved CMS bindings, role previews and no-JavaScript fallback.
- resources/views/layouts/app.blade.php — shared typography and design stylesheet.
- resources/views/layouts/auth.blade.php — shared styling, skip link, corrected role-access wording.
- resources/views/layouts/dashboard.blade.php — shared styling, skip link, navigation label and directory active state.
- resources/views/dashboard/index.blade.php — welcome context before prominent personal water contributions.
- public/css/design-system.css — named design tokens and shared application/authentication styling.
- public/css/landing.css — responsive editorial public design.
- public/js/landing.js — manual CMS message switching, role demonstrations and Escape menu closure.
- public/js/dashboard.js — mobile navigation focus containment, Escape, focus restoration, inert background, resize cleanup and current-page semantics.
- app/Models/SiteContent.php — presentation defaults only; persistence and business logic unchanged.
- tests/Browser/redesign.cjs — public responsive and interaction checks using existing Playwright installation.

## Dependencies and performance

No package added or removed. Reused Blade, plain JavaScript, CSS and the existing Playwright QA installation. Replaced the existing Google Fonts Poppins request with Manrope, retaining local system fallbacks. No new analytics or data integration. No image generation, video, animation framework or continuous animation loop. Preview graphics are lightweight HTML/CSS; animation is limited to short transitions and manual panel changes. Module CSS is intentionally retained to avoid breaking existing workflow-specific presentation.

## Verification

- PHP regression suite: 89 tests, 706 assertions passed (SQLite in memory).
- CMS tests after fallback copy edits: 2 tests, 10 assertions passed.
- Blade compilation passed; changed JavaScript syntax checks passed.
- Chrome responsive audit: 434 checks over 31 page states, with no reported layout/input or JavaScript errors. Includes member, secretary, administrator and Treasurer dashboards; account/profile/security/content screens; authentication; contributions; payment review dialog; queue; reports and receipts.
- Audit widths/heights: 320×800, 360×800, 375×812, 390×844, 412×915, 430×932, 768×1024, 1024×768, 1280×720, 1366×768, 1440×900, 1536×864, 1600×900, 1920×1080.
- Additional public-page checks: widths 320, 360, 375, 390, 393, 412, 430, 480, 768, 1024, 1280, 1440, 1920 at 900px height; role switching, CMS message switching, mobile navigation/Escape, reduced motion and absence of JavaScript errors passed.
- Phone workflows: password reset, temporary-password replacement, independent password toggles/strength/matching, mobile navigation and proof-upload review dialog passed. No actual external payment was made.
- Screenshot review: desktop and phone landing, desktop faculty dashboard, phone login. Artifacts are in storage/app/private/responsive-qa; results.json records the larger audit. QA used existing guarded isolated SQLite fixtures, not live accounts.

## Accessibility and limitations

Added skip links, explicit focus styles, current navigation semantics, mobile focus containment/restoration, background inertness, reduced-motion handling and 16px form input text. Public slides change only on request, so there is no reading deadline. Role selectors use pressed-state buttons and native security disclosures work with keyboard input. Public content has a no-JavaScript fallback.

Chrome was tested in headless desktop/mobile viewport emulation. Safari, Firefox, real phones, screen readers, production deployment, live email delivery and a complete manual accessibility/contrast audit were not verified. The browser workflow checked payment confirmation; backend payment behavior is covered by the existing PHP suite. Not every page received manual screenshot inspection. Existing complex water tables retain local horizontal scrolling where appropriate; other existing tables retain stacked phone rows. Printable/PDF receipt layout was not redesigned or visually reverified.

The reusable visual layer intentionally retains existing module markup, so some deeper screens still use older panel compositions. Saved CMS content can retain outdated module statuses until edited. Recommended next steps: review the club’s saved public copy, then perform real-device and screen-reader acceptance testing with representative faculty users.
