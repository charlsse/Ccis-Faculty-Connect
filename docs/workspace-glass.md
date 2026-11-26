# FacultyConnect authenticated workspace redesign

## Delivered

The existing Laravel/Blade application now shares a compact floating workspace shell, reusable glass materials, readable data surfaces, and coordinated emerald/white/gold light and deep-green dark themes. No framework, route, schema, policy, controller, payment calculation, request field, authentication contract, or database service was changed.

The dashboard puts existing role-aware metrics directly after the personalized welcome. The administrator gets an administration heading and management quick-action label; faculty retain their personal records and contributions. Both retain announcements, collection summaries, transactions, and existing permission boundaries. The official logo and theme mechanism remain unchanged.

## Audit and page coverage

Architecture: Laravel Blade views; shared `layouts.dashboard`; CSS layers loaded after existing module styles; vanilla JavaScript; persisted `appearance.js` theme; existing Canvas 2D microdots. Authentication/onboarding and permission middleware remain in `routes/web.php` and `routes/water.php`. Collection services, PayMongo gateway integration, CSRF, policies, exports, and protected uploads remain untouched.

| Existing page family | Presentation applied |
| --- | --- |
| Faculty/admin dashboard | Role-aware welcome, prioritized live metrics, selective microdots, refined announcements and actions |
| Profile and security | Clear page headers, solid profile/form cards, inputs and focus treatments |
| Directory and user accounts | Consistent cards, filters, status labels, mobile account record stacking |
| Account creation/editing | Stable readable forms and shared controls |
| Announcements/list/detail/editor | Consistent editorial hierarchy, content surfaces, feedback and controls |
| Content manager | Subtle glass category toolbar, solid editing panels |
| Notifications | Elevated glass popover, readable history and filters |
| Collections, checkout, manual records | Shared shell, category controls, readable amount/status panels; existing payment sheets preserved |
| Collection reports, audit, verification, cash, account settings | Unified forms, tables, actions, elevated confirmation dialog |
| Verified receipts | Shared shell and readable receipt; print/export templates retained |
| Existing coming-soon pages | Restrained solid/tinted empty-state surface |

No event module, search endpoint, or statistics were invented. Unsupported inventory/concerns/AI/settings modules remain their existing coming-soon pages. Authentication and landing retain their approved designs.

## Glass system

Extends `premium.css` optical tokens instead of creating a competing material/theme system.

| Material | Placement |
| --- | --- |
| Clear | Floating topbar backdrop and compact controls |
| Regular | Desktop sidebar and mobile drawer |
| Elevated | Profile menu, notification popover, payment confirmation/dialog surfaces |
| Subtle | Payment category rail and content-manager category toolbar |

Materials combine backdrop blur/saturation, translucent tint, directional reflection gradients, masked edge highlights, inner illumination, and layered shadows. Only the topbar edge responds to a fine desktop pointer, through a coalesced animation frame. Text, controls, images, and the official seal are never optically displaced. This is a compatible CSS approximation, not physical refraction or Apple's proprietary rendering. SVG displacement was not added because it would offer little reliable benefit over the edge treatment while risking readability and compatibility.

The topbar is a transparent sticky wrapper with an independent glass child. Menus are siblings of that child, avoiding a filtering ancestor that would limit their backdrop. Removed overflow containment from the main shell lets document content scroll under the sticky bar. This follows [MDN's description of backdrop roots](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Properties/backdrop-filter#backdrop_root).

Unsupported-filter, reduced-transparency, forced-color, and reduced-motion fallbacks are included. Reduced-transparency mode was exercised in Chrome and confirmed an opaque background with no backdrop filter. Unsupported-filter and forced-color rules were code-reviewed, not tested on every browser engine.

## Motion and accessibility

The existing microdot renderer is reused only in dashboard welcome headers. Its text-exclusion mask now includes headings and spans. Offscreen suspension, hidden-tab suspension, lower mobile density, DPR/frame caps, and reduced-motion behavior remain shared. No full-screen dashboard particle background was added.

Short content entrances, tactile buttons, menu entrances, and drawer movement are restrained. Reduced motion disables workspace transitions/animations. Mobile navigation retains scroll locking and focus wrapping/restoration, adds dialog semantics while open, and keeps the inactive page inert. Desktop sidebar transforms no longer animate during breakpoint changes. Form inputs retain their names and validation, with readable mobile font sizes and visible focus indicators. Data tables and long forms have stable surfaces, not backdrop filters.

## Verification

Tested against a loopback-only instance at port 8096 with its own SQLite database, synthetic accounts, array mail, and no production payment actions.

- **416 responsive/theme cases:** 26 authenticated route/role combinations x both themes x 320, 375, 390, 430, 768, 1024, 1440, 1920 widths. No unintended page overflow or small mobile input text; no JavaScript page errors.
- Faculty/admin/treasurer mobile drawer: focus wrapping, inert background, Escape, focus restoration, scroll unlock, open/closed bounds.
- Account and notification menus; 200% browser zoom; live user search/filter.
- Glass scroll evidence: screenshots at two scroll positions plus computed-layer checks. The same empty topbar region changed by mean RGB channel differences of 2.20 (light) and 1.36 (dark) as underlying content changed. Screenshots use viewport clipping to avoid screenshot APIs scrolling the sticky element back into its original position.
- Mobile payment confirmation: viewport bounds, cancel/Escape, restored focus, and preserved inner payment-sheet scrolling.
- Real synthetic password plus two-factor logins; cash recording/receipt creation, receipt layouts, published-collection edit protection, faculty/admin route protection, logout redirect checked by browser workflow scripts.
- Existing landing checks: 16 microdot responsive/theme cases plus visibility, motion and lifecycle checks; 32 landing/auth layout cases and 18 interaction checks.
- Full PHPUnit suite: **134/136 passed, 1,199 assertions**. Existing failures remain: AccountWorkflowTest expects `Trust this device for 20 days` while the view says `Trust this device`; WaterCollectionTest expects `Report & CSV export` while the view says `Report & Excel export`. Neither underlying text was changed here.
- JavaScript syntax checks and Laravel Blade compilation passed.

Screenshots and JSON results: `storage/app/private/workspace-glass-qa/`. Final drawer captures are `drawer-final-light.png` and `drawer-final-dark.png`. Representative dashboard, profile, user-account, content-manager, report, dropdown, scroll, and confirmation screens were visually inspected.

Physical phones, Safari/Firefox, live PayMongo transactions, real email delivery, and every possible data/error state were not exercised. Payment gateways, CRUD permissions, validation, and account workflows are additionally covered by the existing isolated PHPUnit suite. Production data was not changed.

## Exact source changes

- `resources/views/layouts/dashboard.blade.php`: shell, glass layers, role context, page context, assets.
- `resources/views/partials/workspace-icon.blade.php`: meaningful inline navigation SVGs.
- `resources/views/dashboard/index.blade.php`: welcome, metric order, role-aware headings.
- `resources/views/dashboard/partials/notification-menu.blade.php`: elevated material.
- `resources/views/dashboard/content-manager.blade.php`: subtle material toolbar.
- `resources/views/water/partials/navigation.blade.php`: subtle material rail.
- `public/css/workspace.css`: scoped authenticated design system and responsive/fallback refinements.
- `public/js/workspace.js`: selective topbar edge response.
- `public/js/dashboard.js`: mobile drawer dialog semantics.
- `public/js/microdots.js`: expanded text exclusion.

QA/support additions:

- `tools/workspace-glass-environment.php`, `tools/workspace-glass-router.php`, `tools/workspace-glass-fixture.php`.
- `tests/Browser/workspace-shell-preview.cjs`, `workspace-glass.cjs`, `workspace-interactions.cjs`, `workspace-glass-optics.cjs`, `workspace-workflows.cjs`.
- `docs/workspace-glass.md` (this report).

The fixture refuses to overwrite an existing QA database. Initial browser logins save synthetic session states; workflow logout invalidates the faculty state. Run workspace-shell-preview.cjs to refresh expired synthetic sessions (it uses the fixed test-factory TOTP secret). Start with the isolated fixture and loopback router for a new environment; run workflow/logout checks last. Do not point these scripts at a production environment.

