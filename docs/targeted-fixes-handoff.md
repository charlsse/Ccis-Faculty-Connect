# FacultyConnect targeted fixes — September 23, 2026

Implemented locally. No deployment, real invitations, real payments, production migration, dependency installation, or connection changes.

## Evidence and page checklist

The PDF itself was not available in the attachment directory. The page references below come from the supplied task description, not direct inspection of the PDF. Code inspection independently confirmed the described symptoms.

| Reported page | Confirmed in implementation | Change and verification |
| --- | --- | --- |
| 1: reset password | No reveal controls on either password input | Independent non-submit Show/Hide buttons, accessible state, 12-character minimum matching server minimum, duplicate-submit UI guard. Successful Chrome phone submission with and without JavaScript. |
| 1: account creation | Editable status; server allowed creating deactivated accounts | Read-only Pending Activation explanation, preserved role selector and status field. Server forces pending even for tampered active/deactivated values. Tests cover omitted status too. |
| 2: invitation | Basic layout and one-paragraph setup instructions | Inline-styled presentation tables, distinct selectable credentials, role, greeting, button and fallback URL, numbered setup, support fallback, HTML and text alternatives. Expiry is converted to configured timezone; UTC-to-Manila conversion tested. Mail service and transport unchanged. |
| 3: first password replacement | No reveal controls | Same independent controls and submission guard; successful mobile submission with and without JavaScript. |
| 4: dashboard/water | Water summary followed general statistics; no direct personal next action | Personal contribution cards appear first, with actual assigned amount, verified amount, remaining amount, period and status. Existing officer summary is explicitly club-wide. Browser test confirms one tap to instructions and proof form. |

Additional confirmed issue: profile form inputs were below 16 px at tablet width. Dashboard form inputs now use 16 px. Auth controls fit narrow widths; decorative authentication sidebar is hidden on phones. Authorized authenticator setup keeps the manual key and now provides a clipboard button with a manual-selection fallback.

## Account access behavior

New accounts always start Pending Activation, whether an invitation is sent now or later. Invitation delivery is separately displayed and never means the account is active. Existing account management retains its current update endpoint and administrator-password confirmation, with explicit Activate account / Deactivate account options and explanatory text.

Accounts still requiring their temporary-password replacement remain pending when reactivated. Existing permanent-password accounts retain that workflow, and existing middleware still requires missing email verification and 2FA enrollment. This avoids forcing legacy accounts into an unavailable temporary-password workflow. Tests cover password, email and 2FA requirements after reactivation. Existing last-administrator protection, session invalidation, trusted-browser revocation, records and audit services remain unchanged.

## Member water journey

Dashboard → **View Payment Instructions** → pay externally using the existing configured destination → fill the existing proof form → review and submit → pending Treasurer verification → verified receipt or correction/rejection with retained history.

The dashboard shows up to three personal obligations, prioritizing unpaid/rejected/pending records, and links to all periods and history. Pending submissions link directly to their details instead of another payment prompt. Paid/waived cards show zero outstanding for that period. Closed collections explain that submissions are unavailable. Officer cards are personal too; club management capabilities remain available separately.

Existing server rules require exactly the assigned amount, so partial and overpayments remain unsupported. Each collection retains its own obligation and payment destination. Existing locking/reference claims block repeated pending submissions; only approved payments count as verified. Private proofs, ownership checks, upload validation, receipts, rejection reasons and audit history were preserved.

## Verification

- Before editing: PHPUnit **84 tests, 667 assertions**, passed.
- Final: `php packages/phpunit/phpunit/phpunit --no-progress` — **89 tests, 706 assertions**, passed. Includes login, invitations, full onboarding, password changes, TOTP/recovery, trusted devices, profiles, encryption/data protection, role permissions, payment privacy, duplicate prevention, verification and receipts.
- PHP syntax checks for both changed controllers and `node --check public/js/facultyconnect.js` passed.
- Isolated Chrome browser water run: **72 layout checks**, no recorded unexpected console errors. Exercised Treasurer/member/admin login, QR settings, draft visibility, publication, proof submission, correction, same-reference resubmission, approval, PDF receipt, another member's proof denial, cash, waiver, reports/CSV, closure, archive and history.
- Isolated Chrome browser account/UI run: **78 layout checks**, no page errors. Login, reset, temporary-password replacement, 2FA challenge/setup, member/admin/secretary dashboards, member payment form, profile, security, account creation and dummy invitation preview.
- Both runs used **320, 360, 390, 430, 768, 1280 CSS px**. Checks measured page horizontal overflow; account/UI checks also measured visible input font sizes. The water runner checked water input font sizes. Reviewed representative reset, member-dashboard and email screenshots visually.
- Additional Chrome run with JavaScript disabled at 390 px: password reset and temporary-password replacement both submitted successfully.
- Tests used in-memory SQLite or a new private SQLite QA database, synthetic accounts/payments and array/faked mail. Existing QA artifacts were not replaced.

Browser results: [water](../storage/app/private/targeted-qa/browser-results.json), [account/UI](../storage/app/private/targeted-qa/auth-browser-results.json), [without JavaScript](../storage/app/private/targeted-qa/nojs-results.json).

Representative screenshots: [member phone](../storage/app/private/targeted-qa/member-dashboard-390.png), [member desktop](../storage/app/private/targeted-qa/member-dashboard-1280.png), [reset phone](../storage/app/private/targeted-qa/reset-password-390.png), [payment phone](../storage/app/private/targeted-qa/member-water-390.png), [email preview](../storage/app/private/targeted-qa/invitation-preview-390.png).

## Files changed

Application:

- `app/Http/Controllers/FacultyMemberController.php`
- `app/Http/Controllers/DashboardController.php`
- `resources/views/auth/reset-password.blade.php`
- `resources/views/auth/change-temporary-password.blade.php`
- `resources/views/auth/two-factor-setup.blade.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/dashboard/partials/personal-water.blade.php` (new)
- `resources/views/dashboard/members/create.blade.php`
- `resources/views/dashboard/members/partials/form.blade.php`
- `resources/views/mail/invitation.blade.php`
- `resources/views/mail/invitation-text.blade.php`
- `public/css/auth.css`
- `public/css/dashboard.css`
- `public/js/facultyconnect.js`

Verification/documentation:

- `tests/Feature/AccountWorkflowTest.php`
- `tests/Feature/WaterCollectionTest.php`
- `tests/Browser/targeted-water-workflow.cjs` (new; isolated adaptation of existing browser runner)
- `tests/Browser/targeted-auth.cjs` (new)
- `tests/Browser/targeted-nojs.cjs` (new)
- `tools/targeted-browser-environment.php` (new)
- `tools/targeted-browser-fixture.php` (new)
- `tools/targeted-browser-router.php` (new)
- `tools/targeted-auth-fixture.php` (new)
- This handoff; private baseline copies/diff/hash manifest and synthetic QA artifacts under `storage/app/private/`.

## Preservation and setup

This checkout has no Git metadata, so Git status could not establish prior edits. Existing source files were copied to a private baseline before edits and compared afterward. All pre-existing unrelated files were retained. Hash verification found **zero changes to `.env`, configuration files and routes**. Application keys, credentials, database settings, SMTP/Brevo configuration, payment destinations, authentication packages/services, sessions/trust settings and deployment configuration were not edited.

No production setup commands, schema changes or migrations are required. QA helpers explicitly use a separate loopback server and private SQLite database; their process-only overrides do not edit application configuration. The fresh QA fixture refuses to replace an existing QA database. Browser scripts reuse the already-present private Playwright installation and Chrome; no new dependency was installed. Auth fixtures may be regenerated for fresh single-use reset tokens before an individual auth/no-JS run.

## Limits and remaining verification

- Original PDF visual comparison is unavailable.
- Browser checks used desktop Chrome viewport emulation, not physical phones. Safari, Firefox, real virtual keyboards, hardware orientation changes and a full assistive-technology/touch-target audit remain untested.
- Email was rendered with dummy credentials in Chrome, not delivered. Gmail/Outlook and other actual email-client rendering, SMTP delivery and inbox badges remain untested.
- Existing configured APP_URL is loopback-only. Invitation links therefore point back to the receiving device itself and work on the host computer, not another member's phone. It was intentionally left unchanged; providing a reachable deployment URL is a separate configuration decision.
- Real MySQL concurrency, existing live encrypted records, real provider transactions and production integrations were not exercised. Encryption/trust and duplicate-payment checks used isolated fixtures; no claim of production-wide coverage is made.
- No broad redesign or external plugin connection was performed.

## Deferred design proposal

The current interface uses custom CSS, vanilla JavaScript and Blade, with an existing Poppins font; no competing UI framework is needed. A later pass can standardize typography/spacing tokens and reusable Blade form, navigation, contribution-card and status components. Use consistent small accessible icons from one agreed set only where they clarify an action. Figma is an optional later design-review tool requiring installation/connection and an agreed design file; it is not installed or connected as part of this work. None of these proposals is required to use the fixes.
