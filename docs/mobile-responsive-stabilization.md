# Mobile responsiveness stabilization — November 19, 2026

## Status and evidence boundary

Responsive frontend changes are implemented across the shared public, authentication and dashboard layouts, payment selection and HTML receipts. Automated view/backend checks and CSS syntax checks pass. **User iPhone verification passed:** on November 19, 2026, in response to the requested landing/menu/dashboard/Payments/notifications/existing-receipt check, the user confirmed “The checked pages fit correctly.” This confirms the user's checked pages fit without the overlap or sideways page scrolling described in that request; it is not agent-operated visual testing or certification of every role/viewport. No pixel-level browser measurement, screenshot comparison, all-width overflow measurement, or actual mobile PDF download is claimed.

The browser connector returned “The browser access host is unavailable.” Native Chrome returned an accessibility tree, but interaction failed with “coordinate input geometry is unavailable”; its screenshot showed the active VS Code desktop rather than the selected Chrome page. That capture is not mobile evidence. Existing Playwright scripts were not used to bypass the unavailable supported browser interface. No additional browser/framework dependency was installed. A focused iPhone verification request has been sent to the user.

## Arrow and landing fixes

The source audit found a concrete overlap risk in `landing.css`: `.fc-announcement-symbol` was a **70px text arrow**, absolutely positioned at `top:55px; right:35px`, with no mobile size override. The bulletin heading occupies the same area on narrow screens. Other landing arrows were Unicode text spans without intrinsic dimensions. The exact reported iPhone image could not be reproduced, so this is the confirmed source defect, not a claim of screenshot-matched diagnosis.

Landing arrows now use the existing inline-SVG approach through `partials/ui-arrow.blade.php`: explicit 20×20 dimensions, 24×24 viewBox, consistent stroke, decorative accessibility attributes and no new icon library. The bulletin's SVG is bounded to 32px and placed in its own normal-flow row before the heading, eliminating the absolute-position overlap mechanism. Navigation/action arrows are bounded to 20px. Workspace/theme icons also have explicit intrinsic sizes; meaningful brand logos remain intact.

Phone hero typography now uses a rem-based `clamp()` with a 1.875rem lower bound, readable line height and natural wrapping. The shared layer loads **after** the desktop theme so older mobile/desktop cascade rules do not override the repair. Phone side padding, institutional text, hero actions, emblem size and section gaps are more compact. Role-preview labels can wrap independently of icons, including larger text. SVG controls, footer links and navigation retain comfortable tap targets. Desktop hero typography and emerald/glass/gold identity are retained.

## Global components

- Safe-area-aware public/workspace headers, drawer position and bottom padding. The closed drawer remains fully off-screen even with landscape insets. Existing drawer focus trap, Escape/backdrop close, inert background and close-on-navigation logic are retained.
- Shared grid children can shrink instead of forcing page overflow. Admin editor/branding forms become one column on phones; profile, security, member and announcement form containers receive shrink boundaries and wrapping actions.
- Mobile text inputs/selects use at least 16px, with 44px minimum control heights, no zoom-disabling viewport attribute, and scroll margins for focus/keyboard use.
- Payment navigation keeps horizontal scrolling inside its own rail rather than adding multi-row tabs. The active destination is brought into the rail's visible area without scrolling the page. Normal role-aware links and active states are preserved.
- Report tables retain a readable wide layout inside keyboard-focusable scroll regions; they are not compressed into tiny columns. Existing member-table mobile cards remain. Mobile cells, labels and status text are larger. Wallet tables have their own local horizontal scroll boundary.
- Native dialogs receive viewport-relative height limits and scrollable content. A small frontend helper follows visualViewport height/offset for keyboard changes. The existing specialized manual-QR sheet keeps its separate scroll/focus/keyboard implementation.
- Mobile notification panels have constrained height, an independently scrollable list, readable text and touch-sized actions. Footer actions remain outside the scrolling list; the safe-area top offset avoids covering the top bar. Existing notification backend, polling and read-state actions are unchanged.
- Narrow checkout summaries stack the amount when needed; transaction rows and filters adapt at the smallest phone width. Online Payment remains the first option, with Manual QR optional. Pending/success/expired state and amount/verification logic were not modified.
- HTML receipt actions wrap into touch-sized controls, amounts and brand text wrap, and receipt assets have versioned URLs. New receipt rules are screen-only; original A4/print rules and PDF templates remain unchanged.
- Reduced-motion preferences are retained. New CSS does not hide page overflow globally to conceal layout defects.

## Screens audited

| Area | Audit performed |
| --- | --- |
| Landing, login, 2FA/account-security shared shell | Source/CSS review, rendered public/login views, existing authentication/security tests |
| Faculty, Treasurer, Administrator dashboards | Shared-shell review and rendered views for all three roles |
| Profile, security, announcements, notification pages | Rendered views for all three roles; existing functional/authorization tests |
| Member management and content editor | Template/grid/form/table review; existing admin/account tests |
| Collections, contribution details, online/manual QR selection | Template/CSS review and existing payment/navigation tests |
| Transaction history, reports, wallet | Grid/filter/table/navigation review and existing role/payment tests |
| Pending/success/expired views, HTML/PDF receipts | Shared payment/receipt review and existing payment/receipt backend tests |
| Navigation, dropdowns, native dialogs, specialized QR sheet | Existing interaction code reviewed; shared boundaries updated; no actual touch/focus visual journey claimed |

## Verification results

- Full isolated backend/view suite: **230 tests passed / 2,523 assertions**.
- Focused payment/navigation/notification/receipt-related suite after receipt changes: **67 passed / 1,192 assertions**.
- New rendered-view checks: **2 passed / 230 assertions**, confirming last-in-cascade responsive assets, zoom-preserving safe-area viewports, intrinsically bounded decorative SVGs, role-shared layout coverage and retained faculty access restrictions.
- `node --check public/js/responsive.js`: passed.
- Strict installed CSS parser: `responsive.css` and `payment-receipt.css` passed. A leading UTF-8 BOM is normalized for parser compatibility.
- `tools/audit-responsive-css.php` checks width-policy coverage at **320, 375, 390, 430, 768, 1024, 1366 and 1920px**. This is a stylesheet/breakpoint audit, **not a browser layout or screenshot test**. Evidence: `storage/logs/responsive-policy-audit.json`.
- Local and current public HTTPS landing/login plus `/css/responsive.css` and `/js/responsive.js`: all **HTTP 200**. These HTTP checks do not demonstrate mobile layout.

No production account, permission, authentication/2FA setting, connection/secret, database migration, financial record, payment verification, notification backend, tunnel configuration or backup infrastructure was changed. Tests retain the existing guarded SQLite-in-memory environment; no live payment session or real email was created.

## Files changed

- `public/css/responsive.css`, `public/js/responsive.js`: shared responsive layer and small viewport/rail/table helpers.
- `resources/views/layouts/app.blade.php`, `auth.blade.php`, `dashboard.blade.php`: final stylesheet/script placement, cache versioning and safe-area viewport metadata.
- `resources/views/landing.blade.php`, `partials/ui-arrow.blade.php`, `partials/workspace-icon.blade.php`, `partials/theme-toggle.blade.php`: bounded SVG icons and wrapping role labels.
- `resources/views/water/demo/option.blade.php`: bounded payment-option/action SVGs, with original form/action/consent behavior retained.
- `public/css/payment-receipt.css`, `resources/views/water/receipt.blade.php`, `resources/views/water/demo/receipt.blade.php`: screen receipt layout and asset cache versioning.
- `tests/Feature/ResponsivePresentationTest.php`, `tools/audit-responsive-css.php`: isolated view checks and honest static policy audit.
- `docs/mobile-responsive-stabilization.md`: this report.

## Manual verification still required

Use the existing public link and reload to obtain the versioned styles. At each target width above, check portrait/landscape and enlarged text (including approximately 200% text/zoom). These widths were **not visually tested** in this session.

1. Landing: all headings/arrow icons fit, no sideways page scroll, menu/theme controls stay usable, artwork dialog can close.
2. Login/2FA: inputs and validation fit, keyboard leaves the submit/close controls reachable, normal 2FA still works.
3. Each authorized role: drawer opens/closes with focus restored; desktop sidebar remains; menus and notifications stay on-screen.
4. Payments: amount remains readable, online option appears first, manual QR expands correctly, proof input/dialog works with keyboard; inspect existing states without creating a new provider session.
5. History/reports/member lists: local table scrolling or mobile cards expose every column/action; filters remain usable.
6. Existing receipt: HTML fits, PDF download opens on the phone and print content remains complete.
7. Admin/editor/profile forms: long names/labels/errors and larger text wrap without hiding actions.

User phone confirmation for the checked pages is complete: “The checked pages fit correctly.” The exact device width and account role were not specified, and a PDF download was not separately confirmed. The full width/orientation/enlarged-text matrix and remaining role-specific journeys above remain unverified; this report does not claim every possible mobile layout is certified.
