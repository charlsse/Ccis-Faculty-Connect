# Water payment experience redesign

Historical redesign report. The [Treasurer-centered payment architecture](treasurer-payment-workflow.md) supersedes the role permissions, category handling and checkout presentation described below.

Implemented September 28, 2026 in the existing FacultyConnect application.

## Architecture and scope

The original **Collections & Payments** page is the `Payment` general contribution ledger (`payments` table). Officers enter and update records; members can read only their own records. It has no water proof/receipt relationship.

**Water Collections** uses `WaterCollection` → `WaterMember` → `WaterSubmission` → `WaterReceipt`, plus versioned `WaterPaymentAccount`, reference claims, `WaterNotice`, and `AuditLog`. WaterPaymentService owns exact-amount validation, locked financial transitions, duplicate protection, verification, cash entry, waivers, receipts, and notifications. Private proof/QR routes enforce policies. Audit access remains administrator-only.

These serve different purposes. Their database structures and financial responsibilities remain separate; navigation now brings them together. No production database migration, data reset, gateway integration, dependency installation, credential change, deployment, or real message sending was performed. The existing PHP/Blade and public CSS/JavaScript stack was reused; this checkout adds no frontend package or build requirement.

## Navigation and page changes

| Page | Result |
| --- | --- |
| Sidebar | One **Payments & Collections** entry |
| Faculty overview | Personal due cards, Pay Now, pending status, and payment history |
| Officer overview | Personal contributions first, followed by actual club totals |
| Category navigation | Overview, Collections, Verification, Payment Records, QR Accounts, Reports; Audit for administrators only |
| Mobile navigation | Expandable category selector instead of small crowded tabs; ordinary links work without JavaScript |
| Checkout | Separate same-tab personal page, fixed amount, configured QR, return confirmation, receipt upload, honest status |
| Collection details | Overview and Members sections; direct collection Verification and Report links; personal My payment shortcut |
| Members | Name/ID search, status filter, preserved cash/waiver/history actions |
| Collection creation | Basic details, Payment, Assignment; save draft, review amount/due/account/member count, then publish |
| Verification | Visible matching/pending count, collapsible filters, direct Review action |
| Review | Required/submitted amounts, reference, timestamps, private proof, decision and required rejection/correction reason on one page |
| Payment Records | Existing ledger; Add Manual Record reveals the entry form instead of dominating the default page |
| QR Accounts | Existing authorized management with unchanged historical destination behavior |
| Reports | Collection report directory with existing report/CSV functionality |
| Audit | Existing administrator-only history with date, exact action, and actor-ID filters |

## Payment, proof, and retry flow

1. Faculty or an assigned Treasurer opens **Pay Now** from their own contribution card.
2. Review collection, due date, status, and fixed amount.
3. Continue to Payment → the collection's configured QR method → Show payment QR.
4. Scan from another device, or save the QR and import it into a supporting payment app. Recipient and masked account details remain visible. No wallet compatibility or fabricated deep link is asserted.
5. Return and select **I've Completed Payment**. A return dialog can also appear after a document visibility change while the QR step is active.
6. **Yes, I Paid** reveals the existing submission form. Required transaction reference, date/time, JPEG/PNG/PDF proof up to 5 MB, optional note, and confirmation remain enforced. Images receive local previews with Replace/Remove; PDF files show their filename and stay download-only on review. Desktop drag/drop and mobile Photos/Files selection use the native file control.
7. Submit for Verification → confirmation summary → existing protected POST endpoint.
8. **Payment submitted / Pending Verification** appears; the amount does not become verified until an authorized officer approves it.
9. Approval records the existing verifier and timestamp, creates the existing receipt, and sends the existing notification. Correction/rejection shows Needs Attention and the stored reason, with resubmission access and preserved attempt history.

**Not Yet / Payment Failed** only opens retry/help instructions. It makes no financial status change. Show QR Again retries the presentation. Manual instructions direct members to the Treasurer for confirmed alternatives or cash recording; existing cash/waiver actions remain in the Members section. Already-paid members can go directly to proof from the help step.

The code supports **one assigned QR destination per collection**, with one active default used for new collections. Historical accounts remain retained. The redesign deliberately does not present inactive accounts as alternative methods or claim that any configured QR is universally interoperable. Missing QR files produce a contact-the-Treasurer message instead of a broken image or fabricated destination.

Fixed amounts remain the only collection amount model. The readonly field improves the UI; server-side cents comparison remains authoritative. Flexible contributions are deferred. Existing database status values are unchanged: `unpaid`, `pending`, `paid`, `rejected`, and `waived`; their display wording is clearer. Pending amounts are shown separately and explicitly remain included in outstanding balances.

## Security, notifications, and backend reuse

The checkout URL only permits the assigned payer, including officers paying their own contribution. Officer review remains on existing permission-protected routes. The existing CSRF, account/onboarding/2FA middleware, submission throttling, ownership gates, encrypted references/notes, upload validation, private local storage, reference claims, row locks, receipt uniqueness, audit trail, PDF receipts, and CSV exports are retained.

New collection, submission, verification, and correction notifications continue through WaterPaymentService and WaterNotice, already integrated into PortalNotifications and the notification bell. No second notification mechanism or due-reminder scheduler was introduced. Rejection wording is less accusatory. Existing officer self-review policy was not changed; the browser verification exercised Treasurer payment approved by an administrator.

## Routes

Added:

- `GET /dashboard/water/members/{member}/pay` — `water.checkout`, assigned payer only.
- `GET /dashboard/water/reports` — `water.reports`, `payments.manage` required.

Preserved all prior routes. Successful proof POST now redirects to personal checkout. Existing collection show accepts `section=overview|members`, `search`, and `member_status`; audit accepts `date`, `action`, and `actor`. These are read-only filters, not new financial APIs.

## Files

Modified:

- `app/Http/Controllers/WaterCollectionController.php`
- `app/Http/Controllers/WaterPaymentController.php`
- `app/Models/WaterMember.php`
- `app/Services/WaterPaymentService.php`
- `routes/water.php`
- `public/js/water.js`
- `resources/views/layouts/dashboard.blade.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/dashboard/partials/personal-water.blade.php`
- `resources/views/dashboard/payments/index.blade.php`
- `resources/views/water/{index,show,form,queue,review,audit}.blade.php`
- `resources/views/water/partials/{header,payment-form,summary}.blade.php`
- `tests/Feature/WaterCollectionTest.php`

Added application files:

- `public/css/payment-experience.css`
- `resources/views/water/checkout.blade.php`
- `resources/views/water/reports.blade.php`
- `resources/views/water/partials/navigation.blade.php`

Added QA tools: `tests/Browser/payment-redesign.cjs`, `tests/Browser/payment-redesign-final.cjs`, and isolated `tools/payment-redesign-{environment,fixture,router,recovery}.php`. These are standalone CLI/loopback helpers and are not loaded by application routes. Synthetic artifacts reside under `storage/app/private/payment-redesign-qa`. A scoped pre-edit backup is under `storage/app/private/payment-redesign-baseline` because this workspace is not a Git checkout.

## Verification

Current full PHP suite: **108 tests passed, 931 assertions**. Output: `storage/logs/payment-redesign-tests.txt`; JUnit: `storage/logs/payment-redesign-tests.xml`. The suite uses guarded in-memory SQLite; browser tests use a separate SQLite file, synthetic users, and disabled mail. Development MySQL records were not used for test writes.

Primary browser run: **546 layout checks**, no recorded page/console errors, and full create/publish/pay/retry/correct/resubmit/approve/receipt/cash/waiver/report/CSV workflows. Browser additionally denied another faculty member access to private proof and personal checkout. Final refinement run: **182 additional layout checks**, no recorded browser errors, with keyboard/visibility-return, Treasurer-to-administrator verification, audit-filter, and no-JavaScript checks passing. **728 completed layout checks in total.** Final refinement results are recorded in `storage/logs/payment-redesign-final-browser.txt` and `storage/app/private/payment-redesign-qa/final-browser-results.json`.

Browser engine: locally installed Chrome driven headlessly by the existing Playwright dependency. Actual emulated widths in both light/dark themes: **320, 360, 375, 390, 393, 412, 430, 480, 768, 1024, 1280, 1440, 1920**. Layout checks assert no document-wide horizontal overflow and at least 16px visible water-form input text. Representative screenshots are saved at 390px and 1440px for the payment overview, summary, QR, return dialog, proof, pending/paid states, receipt, collection creation/details/members, verification/review, accounts, reports, manual ledger, and audit. Representative screenshots were visually inspected and refined.

Keyboard checks exercise the return dialog, Escape, focus into proof, mobile category selector, and sidebar. The visibility-return test dispatches browser visibility events; it does not claim a real banking-app round trip. JavaScript-disabled checkout inspection verifies accessible QR instructions and the proof form without the step enhancement.

No physical phone, Safari/Firefox, real QR scan/interoperability, real wallet transfer, actual banking-app switching, live email delivery, or screen-reader audit was performed. Full-page modal screenshots may include content outside the viewport backdrop; final return-dialog captures use viewport screenshots.

No installation or database migration is required. Existing Laravel serves the new Blade/CSS/JS assets directly. A browser hard refresh may be needed for previously cached assets. Regression command: `php packages/phpunit/phpunit/phpunit`.

## Future gateway path

If a legitimate provider is later authorized, preserve this checkout presentation and introduce a separate server-side payment-attempt/provider adapter, verified signed webhooks, idempotent processing, amount/currency/recipient validation, and reconciliation. Only trusted provider confirmation should transition a gateway attempt to verified payment. Keep manual proof as a distinct fallback, retaining existing financial records and audit history. No provider API, wallet credential, or gateway dependency was added in this work.
